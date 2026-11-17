<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\MediaUsageInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;

/**
 * The media usage resource's repository. Its queries work on MySQL and PostgreSQL alike
 */
class MediaUsageRepository extends EntityRepository implements MediaUsageRepositoryInterface
{
    public function findByEntry(EntryInterface $entry): array
    {
        /** @var list<MediaUsageInterface> $rows */
        $rows = $this->createQueryBuilder('o')
            ->andWhere('o.entry = :entry')
            ->setParameter('entry', $entry)
            ->getQuery()
            ->getResult()
        ;

        return $rows;
    }

    public function findByMediaItem(string $uuid): array
    {
        /** @var list<MediaUsageInterface> $rows */
        $rows = $this->createQueryBuilder('o')
            ->addSelect('entry')
            ->innerJoin('o.entry', 'entry')
            ->andWhere('o.mediaItemUuid = :uuid')
            ->setParameter('uuid', strtolower($uuid))
            ->addOrderBy('entry.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        return $rows;
    }

    public function countByMediaItems(array $uuids): array
    {
        if ([] === $uuids) {
            return [];
        }

        /** @var list<array{uuid: string, uses: int|string}> $rows */
        $rows = $this->createQueryBuilder('o')
            ->select('o.mediaItemUuid AS uuid', 'COUNT(o.id) AS uses')
            ->andWhere('o.mediaItemUuid IN (:uuids)')
            ->setParameter('uuids', array_values(array_unique(array_map(strtolower(...), $uuids))))
            ->groupBy('o.mediaItemUuid')
            ->getQuery()
            ->getScalarResult()
        ;

        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['uuid']] = (int) $row['uses'];
        }

        return $counts;
    }
}
