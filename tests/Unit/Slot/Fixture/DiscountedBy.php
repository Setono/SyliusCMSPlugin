<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Slot\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsConditionKind;
use Setono\SyliusCMSPlugin\Condition\ConditionKindInterface;
use Setono\SyliusCMSPlugin\Condition\Reads;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * A project's condition kind on the page's product, discounted by an amount or by a percentage, but one of them only
 */
#[AsConditionKind(code: 'discounted_by', label: 'Discounted by', needs: ['product'], reads: [Reads::HostPage], params: DiscountedByParams::class)]
final class DiscountedBy implements ConditionKindInterface
{
    public function matches(object $params, RenderContext $context): bool
    {
        return false;
    }
}
