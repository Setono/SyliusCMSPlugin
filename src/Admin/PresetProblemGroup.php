<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Setono\SyliusCMSPlugin\Problem\PresetProblem;
use Setono\SyliusCMSPlugin\Problem\Severity;

/**
 * The problems of presets of one kind, as the Overview's Needs attention groups them. See "Overview" in docs/spec/admin.md
 */
final readonly class PresetProblemGroup
{
    /**
     * @param string $kind the code of the kind
     * @param Severity $severity an error when one of its problems is, and otherwise a warning
     * @param non-empty-list<PresetProblem> $problems in the order they were found
     */
    public function __construct(
        public string $kind,
        public Severity $severity,
        public array $problems,
    ) {
    }

    /**
     * Groups the problems by kind, in the order the kinds were first found, with the groups of errors first
     *
     * @param iterable<PresetProblem> $problems
     *
     * @return list<self>
     */
    public static function byKind(iterable $problems): array
    {
        /** @var array<string, non-empty-list<PresetProblem>> $byKind */
        $byKind = [];
        foreach ($problems as $problem) {
            $byKind[$problem->kind][] = $problem;
        }

        $errors = $warnings = [];
        foreach ($byKind as $kind => $ofKind) {
            if ([] !== array_filter($ofKind, static fn (PresetProblem $problem): bool => Severity::Error === $problem->severity)) {
                $errors[] = new self($kind, Severity::Error, $ofKind);
            } else {
                $warnings[] = new self($kind, Severity::Warning, $ofKind);
            }
        }

        return [...$errors, ...$warnings];
    }
}
