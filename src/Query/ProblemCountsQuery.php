<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Query;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Setono\SyliusCMSPlugin\Doctrine\EntityManagers;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Problem\ProblemCount;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Problem\TimeDependentProblemKindInterface;

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

    public function ofEntries(array $entries, iterable $kinds, \DateTimeImmutable $now): array
    {
        $ids = array_values(array_filter(array_map(static fn (EntryInterface $entry): ?int => $entry->getId(), $entries), is_int(...)));
        if ([] === $ids) {
            return [];
        }

        $query = $this->manager()->createQueryBuilder()
            ->select('entry.uuid', 'entry.problemErrors AS errors', 'entry.problemWarnings AS warnings')
            ->from(Entry::class, 'entry')
            ->andWhere('entry.id IN (:ids)')
            ->setParameter('ids', $ids)
        ;
        $severities = $this->selectTimeDependent($query, $kinds, $now, 'CASE WHEN %s THEN 1 ELSE 0 END');

        $counts = [];
        /** @var array<string, mixed> $row */
        foreach ($query->getQuery()->getScalarResult() as $row) {
            $counts[self::string($row['uuid'])] = self::count($row, $severities);
        }

        return $counts;
    }

    public function total(iterable $kinds, \DateTimeImmutable $now): ProblemCount
    {
        $query = $this->manager()->createQueryBuilder()
            ->select('SUM(entry.problemErrors) AS errors', 'SUM(entry.problemWarnings) AS warnings')
            ->from(Entry::class, 'entry')
        ;
        $severities = $this->selectTimeDependent($query, $kinds, $now, 'SUM(CASE WHEN %s THEN 1 ELSE 0 END)');

        /** @var array<string, mixed> $row */
        $row = $query->getQuery()->getSingleResult();

        return self::count($row, $severities);
    }

    /**
     * Selects, for each time-dependent kind, whether an entry has a problem of it, as $select says
     *
     * @param iterable<TimeDependentProblemKindInterface> $kinds
     *
     * @return array<string, Severity> the severity of each kind, by the name of what's selected for it
     */
    private function selectTimeDependent(QueryBuilder $query, iterable $kinds, \DateTimeImmutable $now, string $select): array
    {
        $severities = [];
        foreach ($kinds as $kind) {
            $name = sprintf('time_dependent_%d', count($severities));
            $query->addSelect(sprintf('%s AS %s', sprintf($select, $kind->getCondition($query, 'entry', $now)), $name));
            $severities[$name] = $kind->getSeverity();
        }

        return $severities;
    }

    /**
     * The stored counts in a row, with what it selected of each time-dependent kind. Without entries, a sum is null
     *
     * @param array<string, mixed> $row
     * @param array<string, Severity> $severities
     */
    private static function count(array $row, array $severities): ProblemCount
    {
        $errors = self::int($row['errors']);
        $warnings = self::int($row['warnings']);
        foreach ($severities as $name => $severity) {
            if (Severity::Error === $severity) {
                $errors += self::int($row[$name]);
            } else {
                $warnings += self::int($row[$name]);
            }
        }

        return new ProblemCount($errors, $warnings);
    }

    private static function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private static function string(mixed $value): string
    {
        return is_string($value) ? $value : throw new \UnexpectedValueException('An entry\'s UUID must be a string');
    }

    private function manager(): EntityManagerInterface
    {
        return EntityManagers::of($this->managerRegistry, Entry::class);
    }
}
