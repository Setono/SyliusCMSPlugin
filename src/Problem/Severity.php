<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem;

/**
 * How bad a problem is. See "Problems" in docs/spec/admin.md
 */
enum Severity: string
{
    /** Something broken, such as a broken reference or a failed scheduled publish */
    case Error = 'error';

    /** Something to look at, such as a block type that was removed from code */
    case Warning = 'warning';

    /**
     * The problems, of entries or of presets, with the errors first, each in the order given, as the admin lists them
     *
     * @template T of Problem|PresetProblem
     *
     * @param iterable<T> $problems
     *
     * @return list<T>
     */
    public static function errorsFirst(iterable $problems): array
    {
        $errors = $warnings = [];
        foreach ($problems as $problem) {
            if (self::Error === $problem->severity) {
                $errors[] = $problem;
            } else {
                $warnings[] = $problem;
            }
        }

        return [...$errors, ...$warnings];
    }
}
