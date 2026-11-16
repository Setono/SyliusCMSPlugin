<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Query;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Setono\SyliusCMSPlugin\Doctrine\EntityManagers;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Problem\ProblemCount;

/**
 * DQL on the entries' problem count columns, which MySQL and PostgreSQL both run. A list of ids is bound with the
 * array type Doctrine infers, which every DBAL version the plugin allows has. It writes them with bulk updates,
 * which leave the entries Doctrine manages alone, and reads them as scalars, so what an entry object holds of them
 * never counts
 */
final readonly class ProblemCountsQuery implements ProblemCountsQueryInterface
{
    /** How many entries one update names at most */
    private const CHUNK_SIZE = 500;

    public function __construct(private ManagerRegistry $managerRegistry)
    {
    }

    public function store(array $counts, string $fingerprint, bool $onlyStale = false): void
    {
        // One update per count, as most entries share one, such as no problems
        $groups = [];
        foreach ($counts as $id => $count) {
            $groups[sprintf('%d:%d', $count->errors, $count->warnings)][] = $id;
        }

        $manager = $this->manager();
        foreach ($groups as $group => $ids) {
            [$errors, $warnings] = array_map(intval(...), explode(':', $group));

            foreach (array_chunk($ids, self::CHUNK_SIZE) as $chunk) {
                $query = $manager->createQueryBuilder()
                    ->update(Entry::class, 'entry')
                    ->set('entry.problemErrors', ':errors')
                    ->set('entry.problemWarnings', ':warnings')
                    ->set('entry.problemsCountedWith', ':fingerprint')
                    ->andWhere('entry.id IN (:ids)')
                    ->setParameter('errors', $errors, Types::INTEGER)
                    ->setParameter('warnings', $warnings, Types::INTEGER)
                    ->setParameter('fingerprint', $fingerprint, Types::STRING)
                    ->setParameter('ids', $chunk)
                ;

                if ($onlyStale) {
                    $query->andWhere('(entry.problemsCountedWith IS NULL OR entry.problemsCountedWith <> :fingerprint)');
                }

                $query->getQuery()->execute();
            }
        }
    }

    public function ofEntries(array $entries): array
    {
        $ids = array_values(array_filter(array_map(static fn (EntryInterface $entry): ?int => $entry->getId(), $entries), is_int(...)));
        if ([] === $ids) {
            return [];
        }

        /** @var list<array{uuid: string, errors: int|string, warnings: int|string}> $rows */
        $rows = $this->manager()->createQueryBuilder()
            ->select('entry.uuid', 'entry.problemErrors AS errors', 'entry.problemWarnings AS warnings')
            ->from(Entry::class, 'entry')
            ->andWhere('entry.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getScalarResult()
        ;

        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['uuid']] = new ProblemCount((int) $row['errors'], (int) $row['warnings']);
        }

        return $counts;
    }

    public function total(): ProblemCount
    {
        /** @var array{errors: int|string|null, warnings: int|string|null} $row */
        $row = $this->manager()->createQueryBuilder()
            ->select('SUM(entry.problemErrors) AS errors', 'SUM(entry.problemWarnings) AS warnings')
            ->from(Entry::class, 'entry')
            ->getQuery()
            ->getSingleResult()
        ;

        // Without entries, the sums are null
        return new ProblemCount((int) $row['errors'], (int) $row['warnings']);
    }

    private function manager(): EntityManagerInterface
    {
        return EntityManagers::of($this->managerRegistry, Entry::class);
    }
}
