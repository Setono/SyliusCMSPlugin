<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

/**
 * What a code looks like: the code of a block type, a block style, a layout or a content type. Lowercase snake_case,
 * starting with a letter, because codes name columns, CSS classes and stored data
 *
 * @internal
 */
final class Code
{
    public const PATTERN = '/^[a-z][a-z0-9_]*$/';

    public static function isValid(string $code): bool
    {
        return 1 === preg_match(self::PATTERN, $code);
    }
}
