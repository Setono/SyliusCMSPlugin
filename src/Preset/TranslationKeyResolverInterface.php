<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preset;

/**
 * Resolves the translation keys in code presets for one locale. See "What inserting copies" in
 * docs/spec/layouts-and-presets.md
 */
interface TranslationKeyResolverInterface
{
    /**
     * The key's translation in the locale, or null when the translator has none there. Falling back to the store's
     * fallback locale doesn't count, so a locale without a translation stays empty and counts as untranslated
     */
    public function resolve(TranslationKey $key, string $locale): ?string;
}
