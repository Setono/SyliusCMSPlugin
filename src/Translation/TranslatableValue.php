<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Translation;

/**
 * One translatable value of an entry in a locale, with its translation state. See "Translation state" in
 * docs/spec/extending.md
 */
final readonly class TranslatableValue
{
    /**
     * @param string $path where the value is: entry.<field>, layout.<field> or block.<key>.<field>
     * @param string|null $sourceLocaleCode the locale to translate it from: the one it was translated from, or for an
     *                                      untranslated value the first its fallback chain reaches that has a value, or
     *                                      else the first that has one. Null for a value typed without a source
     * @param mixed $sourceValue the source's value now, or null when it has none
     * @param string|null $sourceFingerprint the fingerprint of the source's value now, which a translator passes back to
     *                                       TranslationStateInterface::write(), or null when it has none
     * @param mixed $value the value in the locale, or null when it's missing
     */
    public function __construct(
        public string $path,
        public ValueState $state,
        public ?string $sourceLocaleCode,
        public mixed $sourceValue,
        public ?string $sourceFingerprint,
        public mixed $value,
    ) {
    }
}
