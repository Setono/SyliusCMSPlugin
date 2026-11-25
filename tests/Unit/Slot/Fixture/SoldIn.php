<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Slot\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsConditionKind;
use Setono\SyliusCMSPlugin\Condition\ConditionKindInterface;
use Setono\SyliusCMSPlugin\Condition\Reads;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * A project's condition kind on the page's product, sold in some of the store's channels
 */
#[AsConditionKind(code: 'sold_in', label: 'Sold in', needs: ['product'], reads: [Reads::HostPage], params: SoldInParams::class)]
final class SoldIn implements ConditionKindInterface
{
    public function matches(object $params, RenderContext $context): bool
    {
        return false;
    }
}
