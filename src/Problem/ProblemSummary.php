<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem;

use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * The problems across the store, counted per entry: what the lists' problem marker, their Needs attention filter and
 * the Content badge show. See "Problems" in docs/spec/admin.md
 */
final readonly class ProblemSummary
{
    /**
     * @param array<string, ProblemCount> $entries the count of each entry with a problem, by the entry's UUID
     */
    public function __construct(private array $entries = [])
    {
    }

    public function of(EntryInterface $entry): ProblemCount
    {
        return $this->entries[$entry->getUuid()] ?? new ProblemCount();
    }

    /**
     * The entries that need attention: those with a problem
     *
     * @return list<string> their UUIDs
     */
    public function entryUuids(): array
    {
        return array_keys($this->entries);
    }

    /**
     * The errors and warnings that need attention, which the Content badge counts
     */
    public function total(): ProblemCount
    {
        $errors = $warnings = 0;
        foreach ($this->entries as $count) {
            $errors += $count->errors;
            $warnings += $count->warnings;
        }

        return new ProblemCount($errors, $warnings);
    }
}
