<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem;

use Setono\SyliusCMSPlugin\Preset\Preset;

/**
 * A kind of problem a preset can have, such as blocks of a type the code no longer has. A kind is a service tagged
 * setono_sylius_cms.preset_problem_kind, which autoconfiguration adds, and PresetProblemFinder asks every kind about
 * every preset editors are offered, code presets and editor presets alike. See "Problems" in docs/spec/admin.md
 */
interface PresetProblemKindInterface
{
    /**
     * @return iterable<PresetProblem>
     */
    public function findIn(Preset $preset): iterable;
}
