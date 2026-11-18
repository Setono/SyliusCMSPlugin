<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Preview\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsConditionKind;
use Setono\SyliusCMSPlugin\Condition\ConditionKindInterface;
use Setono\SyliusCMSPlugin\Condition\Reads;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Sylius\Component\Core\Model\ProductInterface;

/**
 * A page-scope condition kind that matches the product page of the product with a code
 */
#[AsConditionKind(code: 'product_code_is', label: 'Product code is', needs: ['product'], reads: [Reads::HostPage], params: ProductCodeIsParams::class)]
final class ProductCodeIs implements ConditionKindInterface
{
    public function matches(object $params, RenderContext $context): bool
    {
        $product = $context->get('product');

        return $params instanceof ProductCodeIsParams && $product instanceof ProductInterface && $product->getCode() === $params->code;
    }
}
