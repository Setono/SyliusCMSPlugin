<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Collection;

use Pagerfanta\Pagerfanta;

/**
 * The page of a paginated Entries block that shows, and the links its <nav class="cms-pagination"> holds: the first
 * and the last page, the pages around the current one, a gap for the pages left out between them, and the previous and
 * next pages, left out on the first and the last page. A gap that would hide only one page links that page instead.
 * See "The Entries block" and "Pagination" in docs/spec/collections.md
 *
 * @template T
 */
final readonly class Pagination
{
    /** How many pages on each side of the current one are linked */
    private const AROUND = 2;

    /**
     * @param Pagerfanta<T> $pager
     * @param list<PageLink> $links in order, with the gaps between them
     */
    private function __construct(
        public Pagerfanta $pager,
        public array $links,
        public ?PageLink $previous,
        public ?PageLink $next,
    ) {
    }

    /**
     * @template I
     *
     * @param Pagerfanta<I> $pager
     * @param \Closure(int<1, max>): string $url the URL of a page
     *
     * @return self<I>
     */
    public static function of(Pagerfanta $pager, \Closure $url): self
    {
        $current = max(1, $pager->getCurrentPage());
        $last = max(1, $pager->getNbPages());

        $links = [];
        $previous = 0;
        foreach (self::linkedPages($current, $last) as $page) {
            if ($page - $previous > 1) {
                $links[] = PageLink::gap();
            }

            $links[] = PageLink::to($page, $url($page), $page === $current);
            $previous = $page;
        }

        return new self(
            $pager,
            $links,
            $current > 1 ? PageLink::to($current - 1, $url($current - 1)) : null,
            $current < $last ? PageLink::to($current + 1, $url($current + 1)) : null,
        );
    }

    /**
     * The entries of the page that shows
     *
     * @return iterable<array-key, T>
     */
    public function entries(): iterable
    {
        return $this->pager->getCurrentPageResults();
    }

    /**
     * The first and the last page and those around the current one, in order, with the one page between two of them
     * added
     *
     * @param int<1, max> $current
     * @param int<1, max> $last
     *
     * @return list<int<1, max>>
     */
    private static function linkedPages(int $current, int $last): array
    {
        $pages = array_unique([1, ...range(max(1, $current - self::AROUND), min($last, $current + self::AROUND)), $last]);
        sort($pages);

        $linked = [];
        foreach ($pages as $page) {
            $before = [] === $linked ? null : $linked[array_key_last($linked)];
            if (null !== $before && 2 === $page - $before) {
                $linked[] = $before + 1;
            }

            $linked[] = $page;
        }

        return $linked;
    }
}
