<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem;

use Doctrine\ORM\QueryBuilder;
use Setono\SyliusCMSPlugin\Query\ProblemCountsQueryInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Before the first read of a request, it has the entries counted again whose counts were made with other code, such as
 * before a deploy that removed a block type, so what it reads is current without anyone running a command
 */
final class ProblemCountProvider implements ProblemCountProviderInterface, ResetInterface
{
    private bool $updated = false;

    public function __construct(
        private readonly ProblemCountsQueryInterface $query,
        private readonly ProblemCountUpdaterInterface $updater,
    ) {
    }

    public function getTotal(): ProblemCount
    {
        $this->updateStale();

        return $this->query->total();
    }

    public function getCounts(iterable $entries): array
    {
        $entries = iterator_to_array($entries, false);
        if ([] === $entries) {
            return [];
        }

        $this->updateStale();
        $stored = $this->query->ofEntries($entries);

        $counts = [];
        foreach ($entries as $entry) {
            $counts[$entry->getUuid()] = $stored[$entry->getUuid()] ?? new ProblemCount();
        }

        return $counts;
    }

    public function getNeedsAttentionCondition(QueryBuilder $queryBuilder, string $entry): string
    {
        $this->updateStale();

        return sprintf('%1$s.problemErrors > 0 OR %1$s.problemWarnings > 0', $entry);
    }

    public function reset(): void
    {
        $this->updated = false;
    }

    private function updateStale(): void
    {
        if (!$this->updated) {
            $this->updated = true;
            $this->updater->updateStale();
        }
    }
}
