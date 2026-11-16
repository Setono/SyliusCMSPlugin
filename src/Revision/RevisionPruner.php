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
        $entry = null;
        $kept = 0;
        foreach ($this->revisions->iterateIdsByEntryNewestFirst() as $revision) {
            if ($revision['entry'] !== $entry) {
                $entry = $revision['entry'];
                $kept = 0;
            }

            if ($kept < $this->keep) {
                ++$kept;
            } else {
                $pruned[] = $revision['id'];
            }
        }

        foreach (array_chunk($pruned, self::BATCH_SIZE) as $batch) {
            $this->revisions->removeByIds($batch);
        }

        return count($pruned);
    }
}
