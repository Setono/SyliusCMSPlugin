<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Collection;

/**
 * The page of a paginated Entries block that a URL asks for with ?page=N. Only a whole number of 1 or more, written
 * without a sign, spaces or leading zeros, is a page, so each page has one URL. See "Pagination" in
 * docs/spec/collections.md
 */
final class PageNumber
{
    /** The query parameter that holds the page */
    public const QUERY_PARAMETER = 'page';

    private function __construct()
    {
    }

    /**
     * The page the value asks for, or null when it isn't one
     *
     * @param mixed $value the query parameter's value, which is an array for page[]=N
     *
     * @return int<1, max>|null
     */
    public static function parse(mixed $value): ?int
    {
        if (!is_string($value) || 1 !== preg_match('/^[1-9][0-9]*$/D', $value)) {
            return null;
        }

        // A number past the largest integer isn't a page either
        $page = filter_var($value, \FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return is_int($page) && $page >= 1 ? $page : null;
    }

    /**
     * The URL of the page of the entry's paginated Entries block: the entry's URL for page 1, and with ?page=N for the
     * others
     *
     * @param string $url the entry's URL
     */
    public static function url(string $url, int $page): string
    {
        if (1 === $page) {
            return $url;
        }

        return sprintf('%s%s%s=%d', $url, str_contains($url, '?') ? '&' : '?', self::QUERY_PARAMETER, $page);
    }
}
