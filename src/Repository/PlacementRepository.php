<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Setono\SyliusCMSPlugin\Model\EntryChannelInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\PlacementInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;

/**
 * The placement resource's repository. A slot's placements at the same position, such as two added at once, are ordered
 * by id, the order they were stored in
 */
class PlacementRepository extends EntityRepository implements PlacementRepositoryInterface
{
    public function findOneByUuid(string $uuid): ?PlacementInterface
    {
        $placement = $this->findOneBy(['uuid' => $uuid]);

        return $placement instanceof PlacementInterface ? $placement : null;
    }

    public function findOneOfSnippet(EntryInterface $snippet, string $uuid): ?PlacementInterface
    {
        $placement = $this->findOneByUuid($uuid);

        return null !== $placement && $placement->getSnippet()->getUuid() === $snippet->getUuid() ? $placement : null;
    }

    public function findBySnippet(EntryInterface $snippet): array
    {
        /** @var list<PlacementInterface> $placements */
        $placements = $this->createQueryBuilder('o')
            ->innerJoin('o.snippet', 'placedSnippet')
            ->andWhere('placedSnippet.uuid = :snippet')
            ->setParameter('snippet', $snippet->getUuid())
            ->addOrderBy('o.slot', 'ASC')
            ->addOrderBy('o.position', 'ASC')
            ->addOrderBy('o.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        return $placements;
    }

    public function findBySlots(array $slots): array
    {
        if ([] === $slots) {
            return [];
        }

        /** @var list<PlacementInterface> $placements */
        $placements = $this->createQueryBuilder('o')
            ->addSelect('placedSnippet')
            ->innerJoin('o.snippet', 'placedSnippet')
            ->andWhere('o.slot IN (:slots)')
            ->setParameter('slots', $slots)
            ->addOrderBy('o.slot', 'ASC')
            ->addOrderBy('o.position', 'ASC')
            ->addOrderBy('o.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        return $placements;
    }

    public function findOutsideChannel(array $slots, string $channelCode): array
    {
        if ([] === $slots) {
            return [];
        }

        /** @var list<PlacementInterface> $placements */
        $placements = $this->createQueryBuilder('o')
            ->addSelect('placedSnippet')
            ->innerJoin('o.snippet', 'placedSnippet')
            ->andWhere('o.slot IN (:slots)')
            ->andWhere(sprintf(
                'NOT EXISTS (SELECT snippetChannel.id FROM %s snippetChannel WHERE snippetChannel.entry = placedSnippet AND snippetChannel.code = :channel)',
                EntryChannelInterface::class,
            ))
            ->setParameter('slots', $slots)
            ->setParameter('channel', $channelCode)
            ->addOrderBy('o.slot', 'ASC')
            ->addOrderBy('o.position', 'ASC')
            ->addOrderBy('o.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        return $placements;
    }

    public function nextPosition(string $slot): int
    {
        $last = $this->createQueryBuilder('o')
            ->select('MAX(o.position)')
            ->andWhere('o.slot = :slot')
            ->setParameter('slot', $slot)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return null === $last ? 0 : (int) $last + 1;
    }
}
