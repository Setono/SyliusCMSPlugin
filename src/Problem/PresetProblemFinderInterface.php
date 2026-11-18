<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem;

use Setono\SyliusCMSPlugin\Preset\Preset;

/**
 * Finds the problems of presets, which aren't entries, so they're found apart from the entries' problems. See "Problems"
 * in docs/spec/admin.md
 */
interface PresetProblemFinderInterface
{
    /**
     * The problems of every preset editors are offered, the errors first
     *
     * @return list<PresetProblem>
     */
    public function findAll(): array;

    /**
     * The problems of one preset, the errors first, such as for a screen that lists presets
     *
     * @return list<PresetProblem>
     */
    public function findIn(Preset $preset): array;

    /**
     * How many errors and warnings every preset has, which the Content badge adds to the entries'
     */
    public function count(): ProblemCount;
}
