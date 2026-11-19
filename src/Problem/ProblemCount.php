<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem;

/**
 * How many errors and warnings there are, of an entry, of the presets or of the whole store
 */
final readonly class ProblemCount
{
    public function __construct(
        public int $errors = 0,
        public int $warnings = 0,
    ) {
    }

    /**
     * @param iterable<Problem|PresetProblem> $problems
     */
    public static function of(iterable $problems): self
    {
        $errors = $warnings = 0;
        foreach ($problems as $problem) {
            if (Severity::Error === $problem->severity) {
                ++$errors;
            } else {
                ++$warnings;
            }
        }

        return new self($errors, $warnings);
    }

    public function total(): int
    {
        return $this->errors + $this->warnings;
    }
}
