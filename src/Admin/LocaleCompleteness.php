<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

/**
 * How many of a channel's entries are complete in one of its locales, as the Overview's Translations shows it. See
 * "Overview" in docs/spec/admin.md
 */
final readonly class LocaleCompleteness
{
    /**
     * @param int $entries how many entries are in the channel
     * @param int $complete how many of them are complete in the locale
     */
    public function __construct(
        public string $localeCode,
        public int $entries,
        public int $complete,
    ) {
    }

    /**
     * How many of the channel's entries aren't complete in the locale: those with no translation there, or with
     * values that are untranslated or outdated there
     */
    public function incomplete(): int
    {
        return $this->entries - $this->complete;
    }
}
