<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Condition\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsConditionKind;
use Setono\SyliusCMSPlugin\Condition\ConditionKindInterface;
use Setono\SyliusCMSPlugin\Condition\Reads;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * A condition kind without parameters that needs the product of the page
 */
#[AsConditionKind(code: 'on_product_page', label: 'On a product page', needs: ['product'], reads: [Reads::HostPage])]
final class OnProductPage implements ConditionKindInterface
{
    public function matches(object $params, RenderContext $context): bool
    {
        return null !== $context->get('product');
    }
}
