<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Field\Products;
use Setono\SyliusCMSPlugin\Value\ItemList;
use Sylius\Component\Core\Model\ProductInterface;

/**
 * A project's block type with a Products field that its template never reads. Only ListSourcesKernel registers it
 */
#[BlockType(code: 'unread_products', label: 'Unread products', template: 'cms/block/unread_products.html.twig')]
final class UnreadProducts
{
    /** @var ItemList<ProductInterface>|null */
    #[Products(label: 'Products')]
    public ?ItemList $products = null;
}
