<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Query;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Setono\SyliusCMSPlugin\Doctrine\EntityManagers;
use Setono\SyliusCMSPlugin\Doctrine\SearchPattern;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryChannelInterface;

/**
 * DQL on the entries' complete locales column, which MySQL and PostgreSQL both run. The column holds the locale codes
 * by code, each between commas, so a locale is found with LIKE, its own _ escaped. It writes the column with bulk
 * updates, which leave the entries Doctrine manages alone, as the problem counts are written
 */
final readonly class CompletenessQuery implements CompletenessQueryInterface
{
    /** How many entries one update names at most */
    private const CHUNK_SIZE = 500;

    private const SEPARATOR = ',';

    public function __construct(private ManagerRegistry $managerRegistry)
    {
    }

    public function store(array $completeLocales): void
    {
        // One update per value, as most entries share one
        $groups = [];
        foreach ($completeLocales as $id => $localeCodes) {
            $groups[self::encode($localeCodes)][] = $id;
        }

        $manager = $this->manager();
        foreach ($groups as $value => $ids) {
            foreach (array_chunk($ids, self::CHUNK_SIZE) as $chunk) {
                $manager->createQueryBuilder()
                    ->update(Entry::class, 'entry')
                    ->set('entry.completeLocales', ':complete_locales')
                    ->andWhere('entry.id IN (:ids)')
                    ->setParameter('complete_locales', $value, Types::TEXT)
                    ->setParameter('ids', $chunk)
                    ->getQuery()
                    ->execute()
                ;
            }
        }
    }

    public function count(array $localesByChannel): array
    {
        if ([] === $localesByChannel) {
            return [];
        }

        $channelCodes = array_map(strval(...), array_keys($localesByChannel));
        $localeCodes = array_values(array_unique(array_merge(...array_values($localesByChannel))));

        $query = $this->manager()->createQueryBuilder()
            ->select('channel.code AS channel_code', 'COUNT(entry.id) AS entries')
            ->from(EntryChannelInterface::class, 'channel')
            ->innerJoin('channel.entry', 'entry')
            ->andWhere('channel.code IN (:channels)')
            ->setParameter('channels', $channelCodes)
            ->groupBy('channel.code')
        ;
        foreach ($localeCodes as $index => $localeCode) {
            $query
                ->addSelect(sprintf('SUM(CASE WHEN entry.completeLocales LIKE :complete_%1$d %2$s THEN 1 ELSE 0 END) AS complete_%1$d', $index, SearchPattern::ESCAPE))
                ->setParameter(sprintf('complete_%d', $index), self::holding($localeCode), Types::STRING)
            ;
        }

        $rows = [];
        /** @var array<string, mixed> $row */
        foreach ($query->getQuery()->getScalarResult() as $row) {
            $rows[self::string($row['channel_code'])] = $row;
        }

        $counts = [];
        foreach ($channelCodes as $channelCode) {
            $row = $rows[$channelCode] ?? [];
            $complete = [];
            foreach ($localesByChannel[$channelCode] as $localeCode) {
                $complete[$localeCode] = self::int($row[sprintf('complete_%d', (int) array_search($localeCode, $localeCodes, true))] ?? 0);
            }

            $counts[$channelCode] = ['entries' => self::int($row['entries'] ?? 0), 'complete' => $complete];
        }

        return $counts;
    }

    public function getIncompleteCondition(QueryBuilder $queryBuilder, string $entry, string $localeCode): string
    {
        $queryBuilder->setParameter('cms_incomplete_locale', self::holding($localeCode), Types::STRING);

        return sprintf('%1$s.completeLocales IS NULL OR %1$s.completeLocales NOT LIKE :cms_incomplete_locale %2$s', $entry, SearchPattern::ESCAPE);
    }

    /**
     * The locale codes by code, each between commas, such as ",da_DK,en_US,", or "," for none
     *
     * @param list<string> $localeCodes
     */
    private static function encode(array $localeCodes): string
    {
        sort($localeCodes);

        return self::SEPARATOR . implode('', array_map(static fn (string $localeCode): string => $localeCode . self::SEPARATOR, $localeCodes));
    }

    /**
     * The pattern of a column that holds the locale
     */
    private static function holding(string $localeCode): string
    {
        return SearchPattern::holding(self::SEPARATOR . $localeCode . self::SEPARATOR);
    }

    private static function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private static function string(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function manager(): EntityManagerInterface
    {
        return EntityManagers::of($this->managerRegistry, Entry::class);
    }
}
