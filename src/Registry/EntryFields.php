<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\EntryTranslationInterface;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;

/**
 * The base fields an entry has, whatever its content type, beside the content type's own: a translation's title, for
 * a content type with its own URL a translation's slug, meta title and meta description, and the shared "Hide from
 * search engines", and for a snippet the shared context it declares. The content type's own fields come with #265.
 * See "Entries" in docs/spec/content-model.md
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
     * The base fields on the entry, shared by all translations: "Hide from search engines" (noindex), true or false,
     * for a content type with its own URL, and a snippet's declared context, a list of context keys
     *
     * @return list<string>
     */
    public static function shared(ContentTypeMetadata $contentType): array
    {
        $fields = $contentType->hasOwnUrl() ? ['noindex'] : [];
        if (is_a($contentType->modelClass, SnippetInterface::class, true)) {
            $fields[] = 'context';
        }

        return $fields;
    }

    /**
     * A shared base field's value on the entry
     *
     * @return bool|list<string>
     *
     * @throws \InvalidArgumentException for a field the entry doesn't have
     */
    public static function readShared(EntryInterface $entry, string $field): bool|array
    {
        return match (true) {
            'noindex' === $field => $entry->isNoindex(),
            'context' === $field && $entry instanceof SnippetInterface => $entry->getContext(),
            default => throw new \InvalidArgumentException(sprintf('"%s" is not one of the shared entry fields of this entry', $field)),
        };
    }

    /**
     * The value a shared base field takes for the value given, which is null for its default: off for noindex, and no
     * context keys, any page, for context
     *
     * @return bool|list<string>
     *
     * @throws \InvalidArgumentException for a field that isn't one of them, or a value that doesn't fit it, whose message
     *                                   says what the field must be, such as "must be true, false or null"
     */
    public static function sharedValue(string $field, mixed $value): bool|array
    {
        return match ($field) {
            'noindex' => null === $value || is_bool($value) ? true === $value : throw new \InvalidArgumentException('must be true, false or null'),
            'context' => self::contextKeys($value),
            default => throw new \InvalidArgumentException(sprintf('"%s" is not one of the shared entry fields', $field)),
        };
    }

    /**
     * Writes a shared base field's value, as sharedValue() gives it, on the entry
     *
     * @param bool|list<string> $value
     *
     * @throws \InvalidArgumentException for a field the entry doesn't have
     */
    public static function writeShared(EntryInterface $entry, string $field, bool|array $value): void
    {
        match (true) {
            'noindex' === $field && is_bool($value) => $entry->setNoindex($value),
            'context' === $field && $entry instanceof SnippetInterface && is_array($value) => $entry->setContext($value),
            default => throw new \InvalidArgumentException(sprintf('"%s" is not one of the shared entry fields of this entry', $field)),
        };
    }

    /**
     * @return list<string>
     */
    private static function contextKeys(mixed $value): array
    {
        if (null === $value) {
            return [];
        }

        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException('must be a list of context keys or null');
        }

        $keys = [];
        foreach ($value as $key) {
            if (!is_string($key) || !Code::isValid($key)) {
                throw new \InvalidArgumentException('must be a list of context keys or null');
            }

            $keys[] = $key;
        }

        return $keys;
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
