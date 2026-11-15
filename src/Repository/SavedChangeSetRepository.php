<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Setono\SyliusCMSPlugin\Builder\ChangeSet\ChangeSet;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\SavedChangeSetInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;

/**
 * The saved change set resource's repository
 */
class SavedChangeSetRepository extends EntityRepository implements SavedChangeSetRepositoryInterface
{
    public function findLatestVersion(EntryInterface $entry): int
    {
        $version = $this->createQueryBuilder('o')
            ->select('MAX(o.version)')
            ->andWhere('o.entry = :entry')
            ->setParameter('entry', $entry)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return is_numeric($version) ? (int) $version : 0;
    }

    public function findChangeSetsSince(EntryInterface $entry, int $version): array
    {
        /** @var list<SavedChangeSetInterface> $saves */
        $saves = $this->createQueryBuilder('o')
            ->andWhere('o.entry = :entry')
            ->andWhere('o.version > :version')
            ->setParameter('entry', $entry)
            ->setParameter('version', $version)
            ->orderBy('o.version', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        return array_map(static fn (SavedChangeSetInterface $save): ChangeSet => ChangeSet::fromArray(CanonicalJson::decode($save->getChanges())), $saves);
    }

    public function record(EntryInterface $entry, int $version, ChangeSet $changeSet, \DateTimeImmutable $savedAt): void
    {
        if (!$entry instanceof Entry) {
            throw new \InvalidArgumentException(sprintf('A save belongs to an entry, which extends %s', Entry::class));
        }

        // The resource's model, which a project can override
        $class = $this->getClassName();
        $this->getEntityManager()->persist(new $class($entry, $version, CanonicalJson::encode($changeSet->toArray()), $savedAt));
    }

    public function removeSavedBefore(EntryInterface $entry, \DateTimeImmutable $before): void
    {
        $this->createQueryBuilder('o')
            ->delete()
            ->andWhere('o.entry = :entry')
            ->andWhere('o.savedAt < :before')
            ->setParameter('entry', $entry)
            ->setParameter('before', $before)
            ->getQuery()
            ->execute()
        ;
    }
}
