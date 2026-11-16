<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Condition\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsConditionKind;
use Setono\SyliusCMSPlugin\Condition\ConditionKindInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * A condition kind whose code isn't a valid code
 */
#[AsConditionKind(code: 'Invalid-Code', label: 'Invalid code')]
final class InvalidCode implements ConditionKindInterface
{
    public function matches(object $params, RenderContext $context): bool
    {
        return true;
    }
}
