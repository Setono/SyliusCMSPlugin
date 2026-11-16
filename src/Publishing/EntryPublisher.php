<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

use Doctrine\Persistence\ObjectManager;
use Psr\Clock\ClockInterface;
use Setono\SyliusCMSPlugin\Event\EntryPublished;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\ScheduledPublishInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Repository\ScheduledPublishRepositoryInterface;
use Setono\SyliusCMSPlugin\Revision\RevisionRecorderInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Publishes now, and runs scheduled publishes. See "Publishing now" and "Scheduled publishes" in
 * docs/spec/publishing.md. Old slugs (#234), the reference table and the media usage index join the steps with their tickets
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
        private ScheduledPublishRepositoryInterface $scheduledPublishes,
        private ObjectManager $manager,
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
            $this->check->check($workingCopy, false);

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
            $this->check->check($workingCopy, true);

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
     * Steps 3 to 8, once the working copy passed the checks
     *
     * @param ScheduledPublishInterface|null $scheduledPublish the scheduled publish this runs, or null for a publish now
     *
     * @return array<string, string> the slugs shoppers saw before, locale => slug
     */
    private function apply(EntryInterface $entry, EntryInterface $workingCopy, ?ScheduledPublishInterface $scheduledPublish): array
    {
        $oldSlugs = $entry->isPublished() ? self::slugs($entry) : [];

        // 3. Copy the working copy onto the rows and delete the draft
        $this->workingCopies->applyToRows($entry, $workingCopy);

        // 7. Store what the rows hold now as a revision. A scheduled one names who scheduled it, since no one is signed in to the worker
        $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
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
            self::slugs($entry),
            $scheduled,
        ));
    }

    /**
     * @return array<string, string> locale => slug, for the translations that have one
     */
    private static function slugs(EntryInterface $entry): array
    {
        $slugs = [];
        foreach ($entry->getTranslations() as $localeCode => $translation) {
            $slug = $translation->getSlug();
            if (null !== $slug) {
                $slugs[$localeCode] = $slug;
            }
        }

        return $slugs;
    }
}
