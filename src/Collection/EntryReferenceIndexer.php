<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Collection;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\EntryReferenceInterface;
use Setono\SyliusCMSPlugin\Repository\EntryReferenceRepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;

final readonly class EntryReferenceIndexer implements EntryReferenceIndexerInterface
{
    /**
     * @param FactoryInterface<EntryReferenceInterface> $referenceFactory
     * @param ObjectManager $manager the manager of the reference table, which is also the manager of entries
     */
    public function __construct(
        private IndexedFields $indexedFields,
        private EntryReferenceRepositoryInterface $references,
        private FactoryInterface $referenceFactory,
        private ObjectManager $manager,
    ) {
    }

    public function index(EntryInterface $entry): void
    {
        // An entry that was never written has no rows yet
        $rows = null === $entry->getId() ? [] : $this->references->findByEntry($entry);
        $changes = EntryReferenceChanges::of($this->indexedFields->in($entry), $rows);

        foreach ($changes->removed as $row) {
            $this->manager->remove($row);
        }

        foreach ($changes->added as [$field, $uuid]) {
            $row = $this->referenceFactory->createNew();
            $row->setEntry($entry);
            $row->setField($field);
            $row->setUuid($uuid);
            $this->manager->persist($row);
        }
    }
}
