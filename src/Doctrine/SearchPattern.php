<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Doctrine;

/**
 * LIKE patterns of what a query looks for, with ESCAPE '!', which MySQL and PostgreSQL both run: anything that holds
 * the text, with its own % and _ taken as they are
 *
 * @internal
 */
final class SearchPattern
{
    public const ESCAPE = "ESCAPE '!'";

    private function __construct()
    {
    }

    /**
     * What an editor typed, to compare with LOWER() of a column: ignoring case and the spaces around it
     */
    public static function containing(string $query): string
    {
        return self::holding(mb_strtolower(trim($query)));
    }

    /**
     * The text exactly as it is, such as a JSON string inside a JSON document
     */
    public static function holding(string $text): string
    {
        return '%' . strtr($text, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
    }
}
