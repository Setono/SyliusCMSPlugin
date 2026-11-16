<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

use Setono\SyliusCMSPlugin\Model\EntryTranslationInterface;

/**
 * The base fields an entry has, whatever its content type, beside the content type's own: a translation's title, and for
 * a content type with its own URL a translation's slug, meta title and meta description, and the shared "Hide from
 * search engines". The content type's own fields come with #265. See "Entries" in docs/spec/content-model.md
 *
 * @internal
 */
final class EntryFields
{
    /**
     * The base fields on the content type's translations, which hold a string or null
     *
     * @return list<string>
     */
    public static function translatable(ContentTypeMetadata $contentType): array
    {
        return $contentType->hasOwnUrl() ? ['title', 'slug', 'metaTitle', 'metaDescription'] : ['title'];
    }

    /**
     * The base fields on the entry, shared by all translations, which hold true or false
     *
     * @return list<string>
     */
    public static function shared(ContentTypeMetadata $contentType): array
    {
        return $contentType->hasOwnUrl() ? ['noindex'] : [];
    }

    /**
     * A translatable base field's value in the translation
     *
     * @throws \InvalidArgumentException for a field that isn't one of them
     */
    public static function read(EntryTranslationInterface $translation, string $field): ?string
    {
        return match ($field) {
            'title' => $translation->getTitle(),
            'slug' => $translation->getSlug(),
            'metaTitle' => $translation->getMetaTitle(),
            'metaDescription' => $translation->getMetaDescription(),
            default => throw self::unknown($field),
        };
    }

    /**
     * Writes a translatable base field's value in the translation
     *
     * @throws \InvalidArgumentException for a field that isn't one of them
     */
    public static function write(EntryTranslationInterface $translation, string $field, ?string $value): void
    {
        match ($field) {
            'title' => $translation->setTitle($value),
            'slug' => $translation->setSlug($value),
            'metaTitle' => $translation->setMetaTitle($value),
            'metaDescription' => $translation->setMetaDescription($value),
            default => throw self::unknown($field),
        };
    }

    private static function unknown(string $field): \InvalidArgumentException
    {
        return new \InvalidArgumentException(sprintf('"%s" is not one of the translatable entry fields', $field));
    }
}
