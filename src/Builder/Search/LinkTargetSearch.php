<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\Search;

use Setono\SyliusCMSPlugin\Link\LinkTargetInterface;

/**
 * What a link target finds, as the search under its code, for a target the plugin has no search of its own for, such
 * as a project's. A link target's search() can't look its refs up, so it finds none of them, and the link control names
 * what a link holds by its ref
 *
 * @internal
 */
final readonly class LinkTargetSearch implements SearchInterface
{
    /** The most results it answers */
    public const LIMIT = 20;

    public function __construct(private LinkTargetInterface $linkTarget)
    {
    }

    public function search(string $query, string $localeCode): array
    {
        $results = [];
        foreach ($this->linkTarget->search($query, $localeCode) as $found) {
            $results[] = new SearchResult($found['ref'], $found['name'], $found['thumbnail']);
            if (self::LIMIT === count($results)) {
                break;
            }
        }

        return $results;
    }

    public function find(array $refs, string $localeCode): array
    {
        return [];
    }
}
