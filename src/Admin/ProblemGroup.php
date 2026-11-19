<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Setono\SyliusCMSPlugin\Problem\PresetProblem;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\Severity;

/**
 * The problems of one kind, of entries or of presets, as the Overview's Needs attention groups them. See "Overview" in
 * docs/spec/admin.md
 *
 * @template T of Problem|PresetProblem
 */
final readonly class ProblemGroup
{
    /**
     * @param string $kind the code of the kind
     * @param Severity $severity an error when one of its problems is, and otherwise a warning
     * @param non-empty-list<T> $problems in the order they were found
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
     * @template P of Problem|PresetProblem
     *
     * @param iterable<P> $problems
     *
     * @return list<self<P>>
     */
    public static function byKind(iterable $problems): array
    {
        /** @var array<string, non-empty-list<P>> $byKind */
        $byKind = [];
        foreach ($problems as $problem) {
            $byKind[$problem->kind][] = $problem;
        }

        $errors = $warnings = [];
        foreach ($byKind as $kind => $ofKind) {
            $error = [] !== array_filter($ofKind, static fn (Problem|PresetProblem $problem): bool => Severity::Error === $problem->severity);
            if ($error) {
                $errors[] = new self($kind, Severity::Error, $ofKind);
            } else {
                $warnings[] = new self($kind, Severity::Warning, $ofKind);
            }
        }

        return [...$errors, ...$warnings];
    }
}
