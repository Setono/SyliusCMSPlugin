<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Application\Condition;

use Setono\SyliusCMSPlugin\Field\Product;
use Sylius\Component\Core\Model\ProductInterface;

final class ProductAvailableParams
{
    #[Product(label: 'app.cms.field.product_available.product', required: true)]
    public ?ProductInterface $product = null;
}
