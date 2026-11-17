<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Query;

use Doctrine\ORM\QueryBuilder;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * The products a shopper may see: enabled, in the channel and translated into the locale, mirroring Sylius's own shop
 * listing. Link targets and sources build on it. See "Visible queries" in docs/spec/sources.md
 */
interface VisibleProductsQueryInterface
{
    /**
     * Root alias "product". No ORDER BY
     */
    public function createQueryBuilder(RenderContext $context): QueryBuilder;
}
