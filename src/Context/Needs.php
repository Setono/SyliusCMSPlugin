<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Context;

/**
 * Matches the context keys something needs, such as a block type, against those provided: by the entry, which provides
 * itself, by what a snippet declares, or by a slot. See "Context keys" in docs/spec/slots.md
 */
final class Needs
{
    private function __construct()
    {
    }

    /**
     * The needed context keys that aren't provided, in the order they're needed
     *
     * @param list<string> $needs
     * @param list<string> $provided
     *
     * @return list<string>
     */
    public static function missing(array $needs, array $provided): array
    {
        return array_values(array_diff($needs, $provided));
    }

    /**
     * Whether every needed context key is provided
     *
     * @param list<string> $needs
     * @param list<string> $provided
     */
    public static function areMet(array $needs, array $provided): bool
    {
        return [] === self::missing($needs, $provided);
    }
}
