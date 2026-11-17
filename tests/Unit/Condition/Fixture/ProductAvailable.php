<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Condition\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsConditionKind;
use Setono\SyliusCMSPlugin\Condition\ConditionKindInterface;
use Setono\SyliusCMSPlugin\Condition\Reads;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * A project's condition kind with a reference parameter: it matches while the product it names is one the shopper may
 * see, which the references loaded before rendering hold
 */
#[AsConditionKind(code: 'product_available', label: 'Product is available', reads: [Reads::Channel, Reads::Locale], params: ProductAvailableParams::class)]
final class ProductAvailable implements ConditionKindInterface
{
    public function matches(object $params, RenderContext $context): bool
    {
        return $params instanceof ProductAvailableParams && null !== $params->product;
    }
}
