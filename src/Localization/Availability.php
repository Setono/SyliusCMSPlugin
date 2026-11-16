<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Localization;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\EntryTranslationInterface;

/**
 * Whether an entry shows in a locale as far as its translations go: only where it has a translation, which it has once
 * that translation has a title, and that translation isn't held back. Whole entries never fall back to another locale.
 * VisibleEntriesQuery filters on the same rule in SQL; the head tags' hreflang links and the overview page's Where it
 * shows read it here. See "Availability" in docs/spec/localization.md and "When an entry shows" in
 * docs/spec/content-model.md
 */
final readonly class Availability
{
    /**
     * Whether the entry has a translation in the locale: one with a title
     */
    public function hasTranslation(EntryInterface $entry, string $localeCode): bool
    {
        return null !== self::translationIn($entry, $localeCode);
    }

    /**
     * Whether the entry has a translation in the locale that isn't held back
     */
    public function showsIn(EntryInterface $entry, string $localeCode): bool
    {
        return true === self::translationIn($entry, $localeCode)?->isShown();
    }

    /**
     * The entry's own translation in the locale, when it has a title
     */
    private static function translationIn(EntryInterface $entry, string $localeCode): ?EntryTranslationInterface
    {
        $translation = $entry->getTranslations()->get($localeCode);
        if (!$translation instanceof EntryTranslationInterface) {
            return null;
        }

        $title = $translation->getTitle();

        return null === $title || '' === $title ? null : $translation;
    }
}
