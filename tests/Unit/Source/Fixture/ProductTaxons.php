<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Source\Fixture;

use Pagerfanta\Adapter\AdapterInterface;
use Pagerfanta\Adapter\ArrayAdapter;
use Setono\SyliusCMSPlugin\Attribute\AsSource;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Source\SourceInterface;
use Sylius\Component\Core\Model\TaxonInterface;

/**
 * A project's taxon source without parameters, which needs the host page's product
 */
#[AsSource(code: 'product_taxons', label: 'app.source.product_taxons', of: TaxonInterface::class, needs: ['product'])]
final class ProductTaxons implements SourceInterface
{
    public function resolve(object $params, RenderContext $context): AdapterInterface
    {
        return new ArrayAdapter([$params]);
    }
}
