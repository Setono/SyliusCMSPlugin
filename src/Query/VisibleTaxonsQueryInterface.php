<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Query;

use Doctrine\ORM\QueryBuilder;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * The taxons a shopper may see: those that are enabled. Link targets and sources build on it. See "Visible queries" in
 * docs/spec/sources.md
 */
interface VisibleTaxonsQueryInterface
{
    /**
     * Root alias "taxon". No ORDER BY
     */
    public function createQueryBuilder(RenderContext $context): QueryBuilder;
}
