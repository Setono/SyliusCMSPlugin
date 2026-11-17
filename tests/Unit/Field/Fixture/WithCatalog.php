<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Field\Fixture;

use Setono\SyliusCMSPlugin\Field\Product;
use Setono\SyliusCMSPlugin\Field\Products;
use Setono\SyliusCMSPlugin\Field\Taxon;
use Setono\SyliusCMSPlugin\Field\Taxons;
use Setono\SyliusCMSPlugin\Value\ItemList;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\TaxonInterface;

final class WithCatalog
{
    #[Product(label: 'app.product')]
    public ?ProductInterface $product = null;

    /** @var ItemList<ProductInterface>|null */
    #[Products(label: 'app.products', max: 12, sources: ['bestsellers'], limit: 4)]
    public ?ItemList $products = null;

    #[Taxon(label: 'app.taxon')]
    public ?TaxonInterface $taxon = null;

    /** @var ItemList<TaxonInterface>|null */
    #[Taxons(label: 'app.taxons', sources: false)]
    public ?ItemList $taxons = null;
}
