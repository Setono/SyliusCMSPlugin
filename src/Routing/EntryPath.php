<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Routing;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\EntryTranslationInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeMetadata;

/**
 * The path of an entry's URL after the locale: "{prefix}/{slug}", or the slug alone for an empty prefix.
 * See "URL shape" in docs/spec/urls-and-seo.md
 */
final class EntryPath
{
    /**
     * Null when the content type has no URL of its own, or the entry has no slug in the locale
     */
    public static function of(ContentTypeMetadata $contentType, EntryInterface $entry, string $localeCode): ?string
    {
        $translation = $entry->getTranslations()->get($localeCode);

        return self::ofSlug($contentType, $translation instanceof EntryTranslationInterface ? $translation->getSlug() : null);
    }

    /**
     * The path of a slug read from elsewhere, such as a draft. Null when the content type has no URL of its own,
     * or there's no slug
     */
    public static function ofSlug(ContentTypeMetadata $contentType, ?string $slug): ?string
    {
        if (null === $contentType->prefix || null === $slug || '' === $slug) {
            return null;
        }

        return '' === $contentType->prefix ? $slug : $contentType->prefix . '/' . $slug;
    }
}
