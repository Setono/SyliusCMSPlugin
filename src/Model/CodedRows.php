<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

/**
 * Brings rows that each hold a code, such as an entry's channels, its audience groups or a page's homepages, to a set of
 * codes: the rows of other codes go, a row is added for each code missing, and the rows of the codes kept stay as they
 * are, so nothing is written for them
 *
 * @internal
 */
final class CodedRows
{
    private function __construct()
    {
    }

    /**
     * @template T of object
     *
     * @param iterable<T> $rows
     * @param array<string> $codes
     * @param callable(T): string $codeOf
     * @param callable(T): void $remove
     * @param callable(string): void $add
     */
    public static function sync(iterable $rows, array $codes, callable $codeOf, callable $remove, callable $add): void
    {
        $kept = [];
        foreach ($rows as $row) {
            $code = $codeOf($row);
            if (in_array($code, $codes, true)) {
                $kept[] = $code;
            } else {
                $remove($row);
            }
        }

        foreach (array_unique(array_diff($codes, $kept)) as $code) {
            $add($code);
        }
    }
}
