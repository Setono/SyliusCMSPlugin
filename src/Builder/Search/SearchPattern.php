<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\Search;

/**
 * The LIKE pattern of what an editor typed, to compare with LOWER() of a column, ESCAPE '!', which MySQL and PostgreSQL
 * both run: anything that holds the query, ignoring case, with the query's own % and _ taken as they are
 *
 * @internal
 */
final class SearchPattern
{
    public const ESCAPE = "ESCAPE '!'";

    public static function containing(string $query): string
    {
        return '%' . strtr(mb_strtolower(trim($query)), ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
    }
}
