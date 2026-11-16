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

    /**
     * Whether a locale condition among a block's conditions hides it in the locale, as it does for every shopper there,
     * whatever else the block's conditions read. The translation counts leave such a block out, because nobody reads it
     * in that locale. A locale code no store locale has can't be the locale, so its parameters aren't hydrated
     *
     * @param list<array<string, mixed>> $conditions the block's conditions, in the stored condition format
     */
    public static function hidesIn(array $conditions, string $localeCode): bool
    {
        foreach ($conditions as $condition) {
            if (self::CODE !== ($condition['kind'] ?? null)) {
                continue;
            }

            $params = $condition['params'] ?? null;
            $locales = is_array($params) ? ($params['locales'] ?? null) : null;
            if ((is_array($locales) && in_array($localeCode, $locales, true)) === (true === ($condition['negate'] ?? false))) {
                return true;
            }
        }

        return false;
    }
}
