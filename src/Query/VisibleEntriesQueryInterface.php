<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Query;

use Doctrine\ORM\QueryBuilder;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * The entries a shopper may see. Listings, sources and URL lookups build on it, so they filter on visibility in SQL,
 * before they page. See "Visible queries" in docs/spec/sources.md
 */
interface VisibleEntriesQueryInterface
{
    /**
     * One content type's entries that are visible in the render context: published, enabled, inside their date window,
     * in the channel, with a translation in the locale, and in the shopper's audience. Root alias "entry". No ORDER BY
     */
    public function createQueryBuilder(string $contentType, RenderContext $context): QueryBuilder;
}
