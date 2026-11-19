<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Problem\Problem;

/**
 * What the Overview's Needs attention shows: the problems of the entries that need attention, grouped by kind, and how
 * many entries need attention in all. See "Overview" in docs/spec/admin.md
 */
final readonly class NeedsAttentionGroups
{
    /**
     * @param list<ProblemGroup<Problem>> $groups
     * @param list<EntryInterface> $entries the entries whose problems the groups hold, the one edited last first
     * @param int $total how many entries need attention, which can be more than those whose problems were found
     */
    public function __construct(
        public array $groups,
        public array $entries,
        public int $total,
    ) {
    }
}
