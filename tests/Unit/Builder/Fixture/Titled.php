<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Builder\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsConditionKind;
use Setono\SyliusCMSPlugin\Condition\ConditionKindInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * A condition kind whose parameter has a field type that's translatable by default
 */
#[AsConditionKind(code: 'titled', label: 'Titled', reads: [], params: TitledParams::class)]
final class Titled implements ConditionKindInterface
{
    public function matches(object $params, RenderContext $context): bool
    {
        return true;
    }
}
