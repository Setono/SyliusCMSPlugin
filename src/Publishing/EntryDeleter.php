<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

use Doctrine\Persistence\ManagerRegistry;
use Setono\SyliusCMSPlugin\Doctrine\EntityManagers;
use Setono\SyliusCMSPlugin\Event\BeforeEntryDelete;
use Setono\SyliusCMSPlugin\Event\EntryDeleted;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The delete runs with the entry's row locked, as autosave and publishing lock it, so no save lands while listeners
 * decide. The database deletes the rows beside the entry with it: its draft, saves and presence
 */
final readonly class EntryDeleter implements EntryDeleterInterface
{
    public function __construct(
        private EntryLock $entryLock,
        private ManagerRegistry $managerRegistry,
        private EventDispatcherInterface $eventDispatcher,
        private ContentTypeRegistryInterface $contentTypes,
    ) {
    }

    public function delete(EntryInterface $entry): void
    {
        $deleted = new EntryDeleted($entry->getUuid(), $this->contentTypes->getByEntry($entry)->code);

        $this->entryLock->write($entry, function () use ($entry): void {
            $veto = $this->eventDispatcher->dispatch(new BeforeEntryDelete($entry))->getVetoMessage();
            if (null !== $veto) {
                throw new DeleteVetoedException($veto);
            }

            EntityManagers::of($this->managerRegistry, $entry::class)->remove($entry);
        });

        // After the transaction commits
        $this->eventDispatcher->dispatch($deleted);
    }
}
