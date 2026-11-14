<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\ChangeSet;

/**
 * What merging a save onto colleagues' saves gives: the changes to apply, and the clashes the editor resolves
 */
final readonly class Merge
{
    /**
     * @param ChangeSet $changeSet the editor's changes that clash with nothing, in order
     * @param list<Clash> $clashes in the order of the editor's changes
     */
    public function __construct(
        public ChangeSet $changeSet,
        public array $clashes,
    ) {
    }
}
