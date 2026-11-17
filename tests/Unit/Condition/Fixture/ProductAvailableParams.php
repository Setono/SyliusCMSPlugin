<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Condition\Fixture;

use Setono\SyliusCMSPlugin\Field\Product;
use Sylius\Component\Core\Model\ProductInterface;

final class ProductAvailableParams
{
    #[Product(label: 'Product')]
    public ?ProductInterface $product = null;
}
