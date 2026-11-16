<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Setono\SyliusCMSPlugin\EntryFormat\InvalidEntryFormatException;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyProviderInterface;
use Setono\SyliusCMSPlugin\Query\ProblemCountsQueryInterface;
use Setono\SyliusCMSPlugin\Tree\InvalidStoredTreeException;

/**
 * A kind that fails on an entry is logged, and the entry keeps the counts it had, so a project's kind never fails a
 * save, nor the admin page that counts stale entries again
 */
final readonly class ProblemCountUpdater implements ProblemCountUpdaterInterface
{
    /** How many stale entries a request counts again at most */
    private const BATCH_SIZE = 100;

    /**
     * @param iterable<ProblemKindInterface> $kinds the kinds whose problems are stored: every kind tagged
     *                                              setono_sylius_cms.problem_kind but the time-dependent ones
     */
    public function __construct(
        private iterable $kinds,
        private WorkingCopyProviderInterface $workingCopies,
        private ProblemCountsQueryInterface $query,
        private ProblemCountFingerprintInterface $fingerprint,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function update(iterable $entries): void
    {
        $counts = $this->count($entries);
        if ([] !== $counts) {
            $this->query->store($counts, $this->fingerprint->get());
        }
    }

    /**
     * It runs on an admin page, so it counts one batch, which the next request after it follows with the next
     */
    public function updateStale(): void
    {
        $this->query->countStale($this->fingerprint->get(), self::BATCH_SIZE, $this->count(...));
    }

    /**
     * @param iterable<EntryInterface> $entries
     *
     * @return array<int, ProblemCount> by the entry's id, of the saved entries every kind could check
     */
    private function count(iterable $entries): array
    {
        $counts = [];
        foreach ($entries as $entry) {
            $id = $entry->getId();
            $count = null === $id ? null : $this->countOf($entry);
            if (null !== $count) {
                $counts[$id] = $count;
            }
        }

        return $counts;
    }

    private function countOf(EntryInterface $entry): ?ProblemCount
    {
        try {
            // A never-published entry's working copy is the entry itself, upgraded as it's read, and Doctrine manages
            // the entry, so a copy is read, which no later flush writes
            $workingCopy = $this->workingCopies->getWorkingCopy(clone $entry);
        } catch (InvalidStoredTreeException|InvalidEntryFormatException) {
            // Content that can't be read at all is one error, which setono:cms:lint reports
            return new ProblemCount(1, 0);
        }

        $problems = [];
        foreach ($this->kinds as $kind) {
            try {
                foreach ($kind->findIn($workingCopy) as $problem) {
                    $problems[] = $problem;
                }
            } catch (\Throwable $exception) {
                $this->logger->error('The problem kind {kind} failed on the entry {uuid}, which keeps the problem counts it had: {message}', [
                    'kind' => $kind::class,
                    'uuid' => $entry->getUuid(),
                    'message' => $exception->getMessage(),
                    'exception' => $exception,
                ]);

                return null;
            }
        }

        return ProblemCount::of($problems);
    }
}
