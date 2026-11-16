<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Revision;

use Setono\SyliusCMSPlugin\Repository\RevisionRepositoryInterface;

/**
 * Deletes the revisions beyond the newest `revisions.keep` of each entry, for setono:cms:revisions:prune.
 * See "Revisions" in docs/spec/publishing.md
 *
 * @internal
 */
final readonly class RevisionPruner
{
    private const BATCH_SIZE = 500;

    /**
     * @param int $keep how many revisions each entry keeps, newest first
     */
    public function __construct(
        private RevisionRepositoryInterface $revisions,
        public int $keep,
    ) {
    }

    /**
     * @return int how many revisions it deleted
     */
    public function prune(): int
    {
        // The ids are all read before any is deleted, so deleting never runs under the reading
        $pruned = [];
        $seen = [];
        foreach ($this->revisions->iterateIdsByEntryNewestFirst() as $revision) {
            $seen[$revision['entry']] = ($seen[$revision['entry']] ?? 0) + 1;
            if ($seen[$revision['entry']] > $this->keep) {
                $pruned[] = $revision['id'];
            }
        }

        foreach (array_chunk($pruned, self::BATCH_SIZE) as $batch) {
            $this->revisions->removeByIds($batch);
        }

        return count($pruned);
    }
}
