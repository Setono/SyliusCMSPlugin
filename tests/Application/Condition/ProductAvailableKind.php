<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Application\Condition;

use Setono\SyliusCMSPlugin\Attribute\AsConditionKind;
use Setono\SyliusCMSPlugin\Condition\ConditionKindInterface;
use Setono\SyliusCMSPlugin\Condition\Reads;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * The test application's own condition kind, as a project adds one, with a product parameter: it matches while the
 * product is one the shopper may see, so a block about it shows only then. The product loads with the page's other
 * references before rendering. #[AsConditionKind] registers it through autoconfiguration
 */
#[AsConditionKind(
    code: 'app_product_available',
    label: 'app.cms.condition_kind.product_available',
    reads: [Reads::Channel, Reads::Locale],
    params: ProductAvailableParams::class,
)]
final class ProductAvailableKind implements ConditionKindInterface
{
    public function matches(object $params, RenderContext $context): bool
    {
        if (!$params instanceof ProductAvailableParams) {
            throw new \InvalidArgumentException(sprintf('The product available condition takes %s, got %s', ProductAvailableParams::class, $params::class));
        }

        return null !== $params->product;
    }
}
