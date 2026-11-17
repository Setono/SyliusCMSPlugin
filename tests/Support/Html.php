<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

/**
 * Compares rendered HTML by its markup, not its indentation
 */
final class Html
{
    private function __construct()
    {
    }

    public static function withoutWhitespaceBetweenTags(string $html): string
    {
        return (string) preg_replace('/>\s+</', '><', trim($html));
    }
}
