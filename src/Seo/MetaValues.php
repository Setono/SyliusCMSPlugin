<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Seo;

use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;

/**
 * The meta title and meta description an entry shows in a locale. Each falls back through the fallback chain first, and
 * only when that leaves it empty to the field it names. See "SEO fields" in docs/spec/urls-and-seo.md
 */
final readonly class MetaValues
{
    public function __construct(
        private ContentTypeRegistryInterface $contentTypes,
        private FallbackValues $fallbackValues,
    ) {
    }

    /**
     * The meta title, or else the title, each through the fallback chain. Null when neither has a value
     */
    public function title(EntryInterface $entry, string $localeCode): ?string
    {
        return $this->value($entry, 'metaTitle', $localeCode) ?? $this->value($entry, 'title', $localeCode);
    }

    /**
     * The meta description, or else the field the content type names in descriptionField, each through the fallback
     * chain. Null when neither has a value, or the content type names no field
     */
    public function description(EntryInterface $entry, string $localeCode): ?string
    {
        $descriptionField = $this->contentTypes->getByEntry($entry)->descriptionField;

        return $this->value($entry, 'metaDescription', $localeCode)
            ?? (null === $descriptionField ? null : $this->value($entry, $descriptionField, $localeCode));
    }

    private function value(EntryInterface $entry, string $field, string $localeCode): ?string
    {
        $value = $this->fallbackValues->entryValue($entry, $field, $localeCode);

        return is_string($value) ? $value : null;
    }
}
