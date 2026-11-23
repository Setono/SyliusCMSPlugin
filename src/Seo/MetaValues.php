<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Seo;

use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Sylius\Component\Core\Model\ChannelInterface;

/**
 * The title and meta description an entry shows in a channel and locale. The meta title and the meta description each
 * fall back through the fallback chain first, and only when that leaves them empty to the field they name. The meta
 * title is printed exactly, and the title it falls back to with the channel's name after it. See "SEO fields" and
 * "Head tags" in docs/spec/urls-and-seo.md
 */
final readonly class MetaValues implements MetaValuesInterface
{
    public function __construct(
        private ContentTypeRegistryInterface $contentTypes,
        private FallbackValues $fallbackValues,
    ) {
    }

    /**
     * The meta title through the fallback chain, exactly, so editors keep full control. Without one, the title through
     * the chain followed by the channel's name, "<title> | <channel name>", as Sylius titles a product page, or whichever
     * of the two has a value. Null when neither has
     */
    public function title(EntryInterface $entry, ChannelInterface $channel, string $localeCode): ?string
    {
        $metaTitle = $this->value($entry, 'metaTitle', $localeCode);
        if (null !== $metaTitle) {
            return $metaTitle;
        }

        $parts = array_filter(
            [$this->value($entry, 'title', $localeCode), $channel->getName()],
            static fn (?string $part): bool => null !== $part && '' !== $part,
        );

        return [] === $parts ? null : implode(' | ', $parts);
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
