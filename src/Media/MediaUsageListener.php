<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Setono\SyliusCMSPlugin\Model\DraftInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\EntryTranslationInterface;
use Setono\SyliusCMSPlugin\Repository\DraftRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\MediaUsageRepositoryInterface;

/**
 * Keeps the media usage index in line with the entries in the flush that writes them, so the index changes in the same
 * transaction as the content: a draft save, a discard, a restore, a publish, a new entry, an upgrade and, later, an
 * import. The rows of an entry the flush deletes go with it, as the database deletes them. See "Usage index" in
 * docs/spec/media.md
 *
 * @internal
 */
final readonly class MediaUsageListener
{
    public function __construct(
        private MediaUsageIndexer $indexer,
        private DraftRepositoryInterface $drafts,
        private MediaUsageRepositoryInterface $usages,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $manager = $args->getObjectManager();
        $unitOfWork = $manager->getUnitOfWork();

        /** @var array<int, EntryInterface> $entries by object id */
        $entries = [];

        /** @var array<int, DraftInterface|null> $drafts the drafts the flush writes, or null for one it deletes, by the entry's object id */
        $drafts = [];

        foreach ([...$unitOfWork->getScheduledEntityInsertions(), ...$unitOfWork->getScheduledEntityUpdates()] as $entity) {
            $entry = self::entryOf($entity);
            if (null !== $entry) {
                $entries[spl_object_id($entry)] = $entry;
            }

            if ($entity instanceof DraftInterface) {
                $drafts[spl_object_id($entity->getEntry())] = $entity;
            }
        }

        $deletions = $unitOfWork->getScheduledEntityDeletions();
        foreach ($deletions as $entity) {
            if ($entity instanceof DraftInterface) {
                $entries[spl_object_id($entity->getEntry())] = $entity->getEntry();
                $drafts[spl_object_id($entity->getEntry())] = null;
            }
        }

        foreach ($deletions as $entity) {
            if ($entity instanceof EntryInterface) {
                unset($entries[spl_object_id($entity)]);
            }
        }

        foreach ($entries as $id => $entry) {
            $draft = array_key_exists($id, $drafts) ? $drafts[$id] : $this->draftOf($entry);
            $this->write($manager, $this->indexer->index($entry, $draft, null === $entry->getId() ? [] : $this->usages->findByEntry($entry)));
        }
    }

    /**
     * Has the flush that runs write the changes too
     */
    private function write(EntityManagerInterface $manager, MediaUsageChanges $changes): void
    {
        $unitOfWork = $manager->getUnitOfWork();

        foreach ($changes->created as $row) {
            $manager->persist($row);
            $unitOfWork->computeChangeSet($manager->getClassMetadata($row::class), $row);
        }

        foreach ($changes->updated as $row) {
            $unitOfWork->recomputeSingleEntityChangeSet($manager->getClassMetadata($row::class), $row);
        }

        foreach ($changes->removed as $row) {
            $manager->remove($row);
        }
    }

    /**
     * The entry's draft as the database has it. Only a published entry has one
     */
    private function draftOf(EntryInterface $entry): ?DraftInterface
    {
        return $entry->isPublished() && null !== $entry->getId() ? $this->drafts->findOneByEntry($entry) : null;
    }

    private static function entryOf(object $entity): ?EntryInterface
    {
        if ($entity instanceof EntryInterface) {
            return $entity;
        }

        if ($entity instanceof DraftInterface) {
            return $entity->getEntry();
        }

        if ($entity instanceof EntryTranslationInterface) {
            $translatable = $entity->getTranslatable();

            return $translatable instanceof EntryInterface ? $translatable : null;
        }

        return null;
    }
}
