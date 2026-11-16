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

    /**
     * The other way round: the slug a path holds for each content type with its own URL whose prefix fits it. A path
     * can fit several, such as blog/hello, which is a page's slug and, under the prefix blog, a blog post's
     *
     * @param iterable<ContentTypeMetadata> $contentTypes
     *
     * @return array<string, string> content type code => the slug the path holds for it
     */
    public static function slugs(iterable $contentTypes, string $path): array
    {
        $slugs = [];

        foreach ($contentTypes as $contentType) {
            if (null === $contentType->prefix) {
                continue;
            }

            if ('' === $contentType->prefix) {
                $slugs[$contentType->code] = $path;

                continue;
            }

            $slug = str_starts_with($path, $contentType->prefix . '/') ? substr($path, strlen($contentType->prefix) + 1) : '';
            if ('' !== $slug) {
                $slugs[$contentType->code] = $slug;
            }
        }

        return $slugs;
    }
}
