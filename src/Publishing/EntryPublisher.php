<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

use Psr\Clock\ClockInterface;
use Setono\SyliusCMSPlugin\Event\BeforeEntryPublish;
use Setono\SyliusCMSPlugin\Event\EntryPublished;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Validation\EntryValidatorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Publishes now. See "Publishing now" in docs/spec/publishing.md. Old slugs (#234), the reference table,
 * the media usage index and revisions (#240) join the steps with their tickets
 */
final readonly class EntryPublisher implements EntryPublisherInterface
{
    public function __construct(
        private WorkingCopyManagerInterface $workingCopies,
        private EntryLock $entryLock,
        private ClockInterface $clock,
        private EventDispatcherInterface $eventDispatcher,
        private ContentTypeRegistryInterface $contentTypes,
        private EntryValidatorInterface $entryValidator,
    ) {
    }

    /**
     * Steps 1 to 8 run in one transaction, with the entry's row locked as autosave locks it, so what's validated and
     * what listeners see is the working copy that's published, and no save lands in between
     */
    public function publish(EntryInterface $entry): void
    {
        $oldSlugs = $this->entryLock->write($entry, function () use ($entry): array {
            $workingCopy = $this->workingCopies->getWorkingCopy($entry);

            // 1. Validation refuses on errors
            $errors = $this->entryValidator->validate($workingCopy);
            if ([] !== $errors) {
                throw new InvalidEntryException($errors);
            }

            // 2. A listener can veto the publish
            $veto = $this->eventDispatcher->dispatch(new BeforeEntryPublish($workingCopy, false))->getVetoMessage();
            if (null !== $veto) {
                throw new PublishVetoedException($veto);
            }

            $oldSlugs = $entry->isPublished() ? self::slugs($entry) : [];

            // 3. and 8. Copy the working copy onto the rows, delete the draft and bump changedAt
            $this->workingCopies->applyToRows($entry, $workingCopy);
            $entry->publish($this->clock->now()->setTimezone(new \DateTimeZone('UTC')));

            return $oldSlugs;
        });

        // 9. After the transaction commits
        $this->eventDispatcher->dispatch(new EntryPublished(
            $entry->getUuid(),
            $this->contentTypes->getByEntry($entry)->code,
            $oldSlugs,
            self::slugs($entry),
            false,
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
