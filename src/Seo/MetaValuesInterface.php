<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Seo;

use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * The meta title and meta description an entry shows in a locale, which its head tags print. See "SEO fields" in
 * docs/spec/urls-and-seo.md
 */
interface MetaValuesInterface
{
    /**
     * The meta title, or else the title, each through the fallback chain. Null when neither has a value
     */
    public function title(EntryInterface $entry, string $localeCode): ?string;

    /**
     * The meta description, or else the field the content type names in descriptionField, each through the fallback
     * chain. Null when neither has a value, or the content type names no field
     */
    public function description(EntryInterface $entry, string $localeCode): ?string;
}
