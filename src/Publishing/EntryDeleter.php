<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

use Doctrine\Persistence\ManagerRegistry;
use Setono\SyliusCMSPlugin\Doctrine\EntityManagers;
use Setono\SyliusCMSPlugin\Event\BeforeEntryDelete;
use Setono\SyliusCMSPlugin\Event\EntryDeleted;
use Setono\SyliusCMSPlugin\Event\PlacementsChanged;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\PlacementInterface;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Repository\PlacementRepositoryInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The delete runs with the entry's row locked, as autosave and publishing lock it, so no save lands while listeners
 * decide. The database deletes the rows beside the entry with it: its draft, saves and presence, and a snippet's
 * placements, so the slots a snippet was placed in are read before the delete, for the PlacementsChanged it dispatches
 * after EntryDeleted
 */
final readonly class EntryDeleter implements EntryDeleterInterface
{
    public function __construct(
        private EntryLock $entryLock,
        private ManagerRegistry $managerRegistry,
        private EventDispatcherInterface $eventDispatcher,
        private ContentTypeRegistryInterface $contentTypes,
        private PlacementRepositoryInterface $placements,
    ) {
    }

    public function delete(EntryInterface $entry): void
    {
        $deleted = new EntryDeleted($entry->getUuid(), $this->contentTypes->getByEntry($entry)->code);

        /** @var list<string> $slots */
        $slots = [];
        $this->entryLock->write($entry, function () use ($entry, &$slots): void {
            $veto = $this->eventDispatcher->dispatch(new BeforeEntryDelete($entry))->getVetoMessage();
            if (null !== $veto) {
                throw new DeleteVetoedException($veto);
            }

            $slots = $this->slotsOf($entry);

            EntityManagers::of($this->managerRegistry, $entry::class)->remove($entry);
        });

        // After the transaction commits
        $this->eventDispatcher->dispatch($deleted);
        if ([] !== $slots) {
            $this->eventDispatcher->dispatch(new PlacementsChanged([$entry->getUuid()], $slots));
        }
    }

    /**
     * The slots a snippet is placed in, read before the delete, as the database deletes its placements with it
     *
     * @return list<string>
     */
    private function slotsOf(EntryInterface $entry): array
    {
        if (!$entry instanceof SnippetInterface) {
            return [];
        }

        return array_values(array_unique(array_map(
            static fn (PlacementInterface $placement): string => $placement->getSlot(),
            $this->placements->findBySnippet($entry),
        )));
    }
}
