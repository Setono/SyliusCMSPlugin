<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

/**
 * An entry's translation in one locale of its channels, as a chip shows it: complete, with its untranslated and
 * outdated counts, held back, or no translation. See "Translation chips" in docs/spec/admin.md
 */
final readonly class TranslationChip
{
    /**
     * @param bool $translated whether the entry has a translation in the locale: one with a title
     * @param bool $heldBack whether the translation is held back
     */
    public function __construct(
        public string $localeCode,
        public bool $translated,
        public bool $heldBack,
        public int $untranslated,
        public int $outdated,
    ) {
    }

    public function isComplete(): bool
    {
        return $this->translated && 0 === $this->untranslated && 0 === $this->outdated;
    }
}
