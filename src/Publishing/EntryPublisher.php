<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Clock\ClockInterface;
use Setono\SyliusCMSPlugin\Event\BeforeEntryPublish;
use Setono\SyliusCMSPlugin\Event\EntryPublished;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Publishes now. See "Publishing now" in docs/spec/publishing.md. Validation (#219), old slugs (#234),
 * the reference table, the media usage index and revisions (#240) join the steps with their tickets
 */
final readonly class EntryPublisher implements EntryPublisherInterface
{
    public function __construct(
        private WorkingCopyManagerInterface $workingCopies,
        private ManagerRegistry $managerRegistry,
        private ClockInterface $clock,
        private EventDispatcherInterface $eventDispatcher,
        private ContentTypeRegistryInterface $contentTypes,
    ) {
    }

    public function publish(EntryInterface $entry): void
    {
        // 2. A listener can veto the publish
        $veto = $this->eventDispatcher->dispatch(new BeforeEntryPublish($this->workingCopies->getWorkingCopy($entry), false))->getVetoMessage();
        if (null !== $veto) {
            throw new PublishVetoedException($veto);
        }

        $manager = $this->managerRegistry->getManagerForClass($entry::class);
        if (!$manager instanceof EntityManagerInterface) {
            throw new \LogicException(sprintf('No Doctrine entity manager manages %s', $entry::class));
        }

        $oldSlugs = $entry->isPublished() ? self::slugs($entry) : [];

        // 3. and 8. Copy the draft onto the rows, delete it and bump changedAt, in one transaction
        $manager->wrapInTransaction(function () use ($entry): void {
            $this->workingCopies->applyDraft($entry);
            $entry->publish($this->clock->now()->setTimezone(new \DateTimeZone('UTC')));
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
