<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Condition\Kind;

use Setono\SyliusCMSPlugin\Attribute\AsConditionKind;
use Setono\SyliusCMSPlugin\Condition\ConditionKindInterface;
use Setono\SyliusCMSPlugin\Condition\Reads;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * Matches when the shopper's locale is one of the locales. See "Condition kinds" in docs/spec/targeting.md
 */
#[AsConditionKind(code: self::CODE, label: 'setono_sylius_cms.condition_kind.locale', reads: [Reads::Locale], params: LocaleParams::class)]
final class LocaleKind implements ConditionKindInterface
{
    public const CODE = 'locale';

    public function matches(object $params, RenderContext $context): bool
    {
        if (!$params instanceof LocaleParams) {
            throw new \InvalidArgumentException(sprintf('The locale condition takes %s, got %s', LocaleParams::class, $params::class));
        }

        return in_array($context->localeCode, $params->locales, true);
    }
}
