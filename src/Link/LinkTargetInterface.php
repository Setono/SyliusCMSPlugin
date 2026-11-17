<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Link;

use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * What a link links to: an entry, a product, a URL or a project's own kind of thing, registered with #[AsLinkTarget].
 * See "Link targets" in docs/spec/extending.md
 */
interface LinkTargetInterface
{
    /**
     * Null when the reference doesn't resolve, or its record isn't visible: the link isn't rendered and is flagged
     *
     * @param string $ref the current locale's ref, which the plugin picked with the fallback chain
     */
    public function resolve(string $ref, RenderContext $context): ?ResolvedLink;

    /**
     * What the builder's picker offers for the query, best first
     *
     * @return iterable<array{ref: string, name: string, thumbnail: ?string}>
     */
    public function search(string $query, string $localeCode): iterable;
}
