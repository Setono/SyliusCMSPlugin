<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Problem\Kind\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsConditionKind;
use Setono\SyliusCMSPlugin\Condition\ConditionKindInterface;
use Setono\SyliusCMSPlugin\Condition\Reads;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * A project's condition kind on the customer, priced for one of the store's customer groups
 */
#[AsConditionKind(code: 'priced_for', label: 'Priced for', reads: [Reads::Customer], params: PricedForParams::class)]
final class PricedFor implements ConditionKindInterface
{
    public function matches(object $params, RenderContext $context): bool
    {
        return false;
    }
}
