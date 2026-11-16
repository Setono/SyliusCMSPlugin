<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Translation;

/**
 * How many of an entry's translatable values are untranslated and outdated in a locale. See "Completeness and outdated
 * flags" in docs/spec/localization.md
 */
final readonly class Completeness
{
    public function __construct(
        public string $localeCode,
        public int $untranslated,
        public int $outdated,
    ) {
    }

    /**
     * @param iterable<TranslatableValue> $values the entry's values in the locale
     */
    public static function of(string $localeCode, iterable $values): self
    {
        $untranslated = 0;
        $outdated = 0;
        foreach ($values as $value) {
            if (ValueState::Untranslated === $value->state) {
                ++$untranslated;
            } elseif (ValueState::Outdated === $value->state) {
                ++$outdated;
            }
        }

        return new self($localeCode, $untranslated, $outdated);
    }
}
