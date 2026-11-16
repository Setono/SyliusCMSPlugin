<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Condition\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsConditionKind;
use Setono\SyliusCMSPlugin\Condition\ConditionKindInterface;
use Setono\SyliusCMSPlugin\Condition\Reads;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * A condition kind like the shipped locale kind, whose locales are fixed choices, so it needs no repository
 */
#[AsConditionKind(code: 'locale_is', label: 'Locale is', reads: [Reads::Locale], params: LocaleIsParams::class)]
final class LocaleIs implements ConditionKindInterface
{
    public function matches(object $params, RenderContext $context): bool
    {
        return $params instanceof LocaleIsParams && in_array($context->localeCode, $params->locales, true);
    }
}
