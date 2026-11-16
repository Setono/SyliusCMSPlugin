<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem;

/**
 * The problems across the store, counted per entry, which the lists' problem marker, their Needs attention filter and
 * the Content badge read
 */
interface ProblemSummaryProviderInterface
{
    public function getSummary(): ProblemSummary;

    /**
     * Drops the summary, so the next read counts again, as after an entry is written
     */
    public function clear(): void;
}
