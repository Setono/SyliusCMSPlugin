<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

use Doctrine\Persistence\ManagerRegistry;
use Setono\SyliusCMSPlugin\Doctrine\EntityManagers;
use Setono\SyliusCMSPlugin\Model\EntryChannelInterface;
use Setono\SyliusCMSPlugin\Model\PlacementInterface;
use Sylius\Component\Core\Model\ChannelInterface;

/**
 * Reads the channel's placements from the database with one query, as plain values, without loading their snippets
 */
final readonly class PlacementProvider implements PlacementProviderInterface
{
    /**
     * @param class-string<PlacementInterface> $placementClass the placement resource's model
     */
    public function __construct(
        private ManagerRegistry $managerRegistry,
        private string $placementClass,
    ) {
    }

    public function getPlacements(ChannelInterface $channel): array
    {
        /** @var list<array{uuid: string, snippetUuid: string, slot: string, pageScope: list<array<string, mixed>>, position: int}> $rows */
        $rows = EntityManagers::of($this->managerRegistry, $this->placementClass)->createQueryBuilder()
            ->select('placement.uuid AS uuid', 'placedSnippet.uuid AS snippetUuid', 'placement.slot AS slot', 'placement.pageScope AS pageScope', 'placement.position AS position')
            ->from($this->placementClass, 'placement')
            ->innerJoin('placement.snippet', 'placedSnippet')
            ->andWhere(sprintf(
                'EXISTS (SELECT snippetChannel.id FROM %s snippetChannel WHERE snippetChannel.entry = placedSnippet AND snippetChannel.code = :channel)',
                EntryChannelInterface::class,
            ))
            ->setParameter('channel', (string) $channel->getCode())
            ->addOrderBy('placement.slot', 'ASC')
            ->addOrderBy('placement.position', 'ASC')
            ->addOrderBy('placement.id', 'ASC')
            ->getQuery()
            ->getArrayResult()
        ;

        return array_map(static fn (array $row): PlacementData => new PlacementData(
            $row['uuid'],
            $row['snippetUuid'],
            $row['slot'],
            $row['pageScope'],
            $row['position'],
        ), $rows);
    }
}
