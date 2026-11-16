<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Condition\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsConditionKind;
use Setono\SyliusCMSPlugin\Condition\ConditionKindInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * A condition kind whose parameters class doesn't exist
 */
// @phpstan-ignore argument.type (the class doesn't exist, on purpose)
#[AsConditionKind(code: 'missing_params', label: 'Missing parameters', params: 'App\Condition\MissingParams')]
final class MissingParams implements ConditionKindInterface
{
    public function matches(object $params, RenderContext $context): bool
    {
        return true;
    }
}
