<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

use Doctrine\Persistence\ObjectManager;
use Psr\Clock\ClockInterface;
use Setono\SyliusCMSPlugin\Collection\EntryReferenceIndexerInterface;
use Setono\SyliusCMSPlugin\Event\EntryPublished;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\FillsOnPublishInterface;
use Setono\SyliusCMSPlugin\Model\ScheduledPublishInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Repository\ScheduledPublishRepositoryInterface;
use Setono\SyliusCMSPlugin\Revision\RevisionRecorderInterface;
use Setono\SyliusCMSPlugin\Routing\OldSlugRecorderInterface;
use Setono\SyliusCMSPlugin\Routing\Slug;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Publishes now, and runs scheduled publishes. See "Publishing now" and "Scheduled publishes" in
 * docs/spec/publishing.md. The media usage index (step 6) is written by a Doctrine listener, when the rows are
 */
final readonly class EntryPublisher implements EntryPublisherInterface
{
    /**
     * @param ObjectManager $manager the manager of scheduled publishes, which is also the manager of entries
     */
    public function __construct(
        private WorkingCopyManagerInterface $workingCopies,
        private EntryLock $entryLock,
        private ClockInterface $clock,
        private EventDispatcherInterface $eventDispatcher,
        private ContentTypeRegistryInterface $contentTypes,
        private PublishCheck $check,
        private RevisionRecorderInterface $revisions,
        private OldSlugRecorderInterface $oldSlugs,
        private ScheduledPublishRepositoryInterface $scheduledPublishes,
        private ObjectManager $manager,
        private EntryReferenceIndexerInterface $referenceIndexer,
    ) {
    }

    /**
     * Steps 1 to 8 run in one transaction, with the entry's row locked as autosave locks it, so what's validated and
     * what listeners see is the working copy that's published, and no save lands in between. The entry's scheduled
     * publish ends with it: one that waits would have nothing left to publish, and a failed one is done with
     */
    public function publish(EntryInterface $entry): void
    {
        $oldSlugs = $this->entryLock->write($entry, function () use ($entry): array {
            $workingCopy = $this->workingCopies->getWorkingCopy($entry);

            // 1 and 2. Validation refuses on errors, and a listener can veto the publish
            $this->check->check($entry, $workingCopy, false);

            $scheduledPublish = $this->scheduledPublishes->findOneByEntry($entry);
            if (null !== $scheduledPublish) {
                $this->manager->remove($scheduledPublish);
            }

            return $this->apply($entry, $workingCopy, null);
        });

        $this->published($entry, $oldSlugs, false);
    }

    /**
     * Validation and the listeners run again, on the working copy as it is now, and the steps of a publish follow in one
     * transaction, as for a publish now. The entry's scheduled publish is checked in that transaction, so a cancel or a
     * replace that lands first leaves its message nothing to do
     */
    public function publishScheduled(EntryInterface $entry, string $token): bool
    {
        $oldSlugs = $this->entryLock->write($entry, function () use ($entry, $token): ?array {
            $scheduledPublish = $this->scheduledPublishes->findOneByEntry($entry);
            if (null === $scheduledPublish || $scheduledPublish->isFailed() || $token !== $scheduledPublish->getToken()) {
                return null;
            }

            $workingCopy = $this->workingCopies->getWorkingCopy($entry);
            $this->check->check($entry, $workingCopy, true);

            $this->manager->remove($scheduledPublish);

            return $this->apply($entry, $workingCopy, $scheduledPublish);
        });

        if (null === $oldSlugs) {
            return false;
        }

        $this->published($entry, $oldSlugs, true);

        return true;
    }

    /**
     * Steps 3 to 8, once the working copy passed the checks, but 6, the media usage index, which a Doctrine listener
     * writes when the rows are flushed
     *
     * @param ScheduledPublishInterface|null $scheduledPublish the scheduled publish this runs, or null for a publish now
     *
     * @return array<string, string> the slugs shoppers saw before, locale => slug
     */
    private function apply(EntryInterface $entry, EntryInterface $workingCopy, ?ScheduledPublishInterface $scheduledPublish): array
    {
        $oldSlugs = $entry->isPublished() ? Slug::of($entry) : [];
        $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));

        // 3. Copy the working copy onto the rows and delete the draft. The content type fills its values, such as a blog
        // post's date on its first publish, so the revision and shoppers see them
        $this->workingCopies->applyToRows($entry, $workingCopy);
        if ($entry instanceof FillsOnPublishInterface) {
            $entry->fillOnPublish($now);
        }

        // 4. Record the old slug of every translation whose slug changed, so it redirects
        $this->oldSlugs->record($entry, $oldSlugs, Slug::of($entry));

        // 5. Write the UUIDs of the indexed Entries fields, such as a post's categories, to the reference table
        $this->referenceIndexer->index($entry);

        // 7. Store what the rows hold now as a revision. A scheduled one names who scheduled it, since no one is signed in to the worker
        $this->revisions->record($entry, $now, null !== $scheduledPublish, $scheduledPublish?->getScheduledBy());

        // 8. Bump changedAt
        $entry->publish($now);

        return $oldSlugs;
    }

    /**
     * 9. After the transaction commits
     *
     * @param array<string, string> $oldSlugs
     */
    private function published(EntryInterface $entry, array $oldSlugs, bool $scheduled): void
    {
        $this->eventDispatcher->dispatch(new EntryPublished(
            $entry->getUuid(),
            $this->contentTypes->getByEntry($entry)->code,
            $oldSlugs,
            Slug::of($entry),
            $scheduled,
        ));
    }
}
