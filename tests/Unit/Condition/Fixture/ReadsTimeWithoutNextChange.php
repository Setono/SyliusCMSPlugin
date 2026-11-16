<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Condition\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsConditionKind;
use Setono\SyliusCMSPlugin\Condition\ConditionKindInterface;
use Setono\SyliusCMSPlugin\Condition\Reads;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * A condition kind that reads the time without reporting its next change
 */
#[AsConditionKind(code: 'clock', label: 'Clock', reads: [Reads::Customer, Reads::Time])]
final class ReadsTimeWithoutNextChange implements ConditionKindInterface
{
    public function matches(object $params, RenderContext $context): bool
    {
        return true;
    }
}
