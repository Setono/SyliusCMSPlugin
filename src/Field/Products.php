<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Setono\SyliusCMSPlugin\Attribute\FieldType;
use Sylius\Component\Core\Model\ProductInterface;

/**
 * Products, hand-picked by code in the order editors put them, or filled by a product source. Hydrated as
 * ItemList<ProductInterface> of the products the shopper may see. See "Shipped field types" in docs/spec/extending.md
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
#[FieldType(code: 'products')]
final class Products extends ListField
{
    public function itemType(): string
    {
        return 'product';
    }

    public function itemClass(): string
    {
        return ProductInterface::class;
    }
}
