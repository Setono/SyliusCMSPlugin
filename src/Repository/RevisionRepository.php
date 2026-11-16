<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\RevisionInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;

/**
 * The revision resource's repository. Revisions of one publish time are ordered by id, the order they were stored in
 */
class RevisionRepository extends EntityRepository implements RevisionRepositoryInterface
{
    public function findOneById(int $id): ?RevisionInterface
    {
        $revision = $this->find($id);

        return $revision instanceof RevisionInterface ? $revision : null;
    }

    public function findByEntry(EntryInterface $entry): array
    {
        /** @var list<RevisionInterface> $revisions */
        $revisions = $this->createQueryBuilder('o')
            ->andWhere('o.entry = :entry')
            ->setParameter('entry', $entry)
            ->addOrderBy('o.publishedAt', 'DESC')
            ->addOrderBy('o.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        return $revisions;
    }

    public function iterateIdsByEntryNewestFirst(): iterable
    {
        $rows = $this->createQueryBuilder('o')
            ->select('IDENTITY(o.entry) AS entry', 'o.id AS id')
            ->addOrderBy('o.entry', 'ASC')
            ->addOrderBy('o.publishedAt', 'DESC')
            ->addOrderBy('o.id', 'DESC')
            ->getQuery()
            ->toIterable()
        ;

        foreach ($rows as $row) {
            /** @var array{entry: int|string, id: int|string} $row */
            yield ['entry' => (int) $row['entry'], 'id' => (int) $row['id']];
        }
    }

    public function removeByIds(array $ids): void
    {
        if ([] === $ids) {
            return;
        }

        $this->createQueryBuilder('o')
            ->delete()
            ->andWhere('o.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->execute()
        ;
    }
}
