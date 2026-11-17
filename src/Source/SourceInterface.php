<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Source;

use Pagerfanta\Adapter\AdapterInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * Fills a list field when content is shown, such as with the products in a taxon. Register it with #[AsSource].
 *
 * It takes no limit and no offset: the list field that holds it pages it, and a list that doesn't paginate takes the
 * first slice and never counts. Its order is stable and ends in a unique tie-breaker, such as the ID, so pages never
 * overlap or skip items. A source built on Doctrine returns a QueryAdapter on the visible queries
 * (Setono\SyliusCMSPlugin\Query\Visible*QueryInterface), so paging, counting and visibility filtering happen in SQL.
 * It reads the shopper, channel, locale and time only from the render context.
 * See "The contract" in docs/spec/sources.md and ADR 0022
 */
interface SourceInterface
{
    /**
     * @param object $params its parameters class, hydrated from what editors filled in, or a NoParams
     *
     * @return AdapterInterface<object> what it lists, as its #[AsSource] "of" says
     */
    public function resolve(object $params, RenderContext $context): AdapterInterface;
}
