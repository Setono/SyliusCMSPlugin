<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Collection;

/**
 * A link to one page of a paginated Entries block, or a gap in the page numbers. See "The Entries block" in
 * docs/spec/collections.md
 */
final readonly class PageLink
{
    /**
     * @param int<1, max>|null $page the page it links to, or null for a gap
     * @param string|null $url the page's URL, or null for a gap
     * @param bool $current whether it's the page that shows
     */
    private function __construct(
        public ?int $page,
        public ?string $url,
        public bool $current,
    ) {
    }

    /**
     * @param int<1, max> $page
     */
    public static function to(int $page, string $url, bool $current = false): self
    {
        return new self($page, $url, $current);
    }

    /**
     * The pages left out between two links
     */
    public static function gap(): self
    {
        return new self(null, null, false);
    }
}
