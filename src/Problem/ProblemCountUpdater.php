<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem;

use Setono\SyliusCMSPlugin\EntryFormat\InvalidEntryFormatException;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyProviderInterface;
use Setono\SyliusCMSPlugin\Query\ProblemCountsQueryInterface;
use Setono\SyliusCMSPlugin\Tree\InvalidStoredTreeException;

final readonly class ProblemCountUpdater implements ProblemCountUpdaterInterface
{
    /**
     * @param iterable<ProblemKindInterface> $kinds the kinds whose problems are stored: every kind tagged
     *                                              setono_sylius_cms.problem_kind but the time-dependent ones
     */
    public function __construct(
        private iterable $kinds,
        private WorkingCopyProviderInterface $workingCopies,
        private ProblemCountsQueryInterface $query,
        private ProblemCountFingerprintInterface $fingerprint,
    ) {
    }

    public function update(iterable $entries): void
    {
        $counts = $this->count($entries);
        if ([] !== $counts) {
            $this->query->store($counts, $this->fingerprint->get());
        }
    }

    public function updateStale(): void
    {
    }

    /**
     * @param iterable<EntryInterface> $entries
     *
     * @return array<int, ProblemCount> by the entry's id
     */
    private function count(iterable $entries): array
    {
        $counts = [];
        foreach ($entries as $entry) {
            $id = $entry->getId();
            if (null !== $id) {
                $counts[$id] = $this->countOf($entry);
            }
        }

        return $counts;
    }

    private function countOf(EntryInterface $entry): ProblemCount
    {
        try {
            return ProblemCount::of($this->problemsOf($this->workingCopies->getWorkingCopy($entry)));
        } catch (InvalidStoredTreeException|InvalidEntryFormatException) {
            // Content that can't be read at all fails where it's opened, not in the counts
            return new ProblemCount();
        }
    }

    /**
     * @return \Generator<Problem>
     */
    private function problemsOf(EntryInterface $workingCopy): \Generator
    {
        foreach ($this->kinds as $kind) {
            yield from $kind->findIn($workingCopy);
        }
    }
}
