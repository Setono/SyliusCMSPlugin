<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Condition\Kind;

use Setono\SyliusCMSPlugin\Field\Products;
use Setono\SyliusCMSPlugin\Value\ItemList;
use Sylius\Component\Core\Model\ProductInterface;

final class ProductParams
{
    /** @var ItemList<ProductInterface>|null the picked products the shopper may see, stored as product codes */
    #[Products(label: 'setono_sylius_cms.field.product_condition.products', sources: false, required: true)]
    public ?ItemList $products = null;
}
