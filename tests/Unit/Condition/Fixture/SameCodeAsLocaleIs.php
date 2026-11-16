<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Condition\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsConditionKind;
use Setono\SyliusCMSPlugin\Condition\ConditionKindInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * A condition kind with the code of LocaleIs
 */
#[AsConditionKind(code: 'locale_is', label: 'Same code as LocaleIs')]
final class SameCodeAsLocaleIs implements ConditionKindInterface
{
    public function matches(object $params, RenderContext $context): bool
    {
        return true;
    }
}
