<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Slot\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsConditionKind;
use Setono\SyliusCMSPlugin\Condition\ConditionKindInterface;
use Setono\SyliusCMSPlugin\Condition\Reads;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * A project's condition kind on the page's product, whose parameters are a span of dates that must end after it starts
 */
#[AsConditionKind(code: 'released_between', label: 'Released between', needs: ['product'], reads: [Reads::HostPage], params: ReleasedBetweenParams::class)]
final class ReleasedBetween implements ConditionKindInterface
{
    public function matches(object $params, RenderContext $context): bool
    {
        return false;
    }
}
