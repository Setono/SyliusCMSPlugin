<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preset;

use Setono\SyliusCMSPlugin\Field\RichText;
use Setono\SyliusCMSPlugin\Registry\FieldMetadata;
use Setono\SyliusCMSPlugin\RichText\RichTextSchema;

/**
 * Turns the translation keys in a code preset's field values into locale maps: what inserting does with them, and what
 * setono:cms:preset:validate checks the values as. A rich text field's translation is HTML. See "Field values" in
 * docs/spec/content-model.md
 */
final readonly class TranslationKeyValues
{
    public function __construct(private RichTextSchema $richText)
    {
    }

    /**
     * Replaces each translation key in a field's value, wherever it sits, such as a Link's label or a binding's
     * fallback, with a map of its translations in the locales that have one, which is {} when none has
     *
     * @param list<string> $locales
     * @param callable(TranslationKey, string): ?string $translate the key's translation in a locale, or null when it has none
     */
    public function resolve(FieldMetadata $field, mixed $value, array $locales, callable $translate): mixed
    {
        return $this->resolveIn($value, $field->field instanceof RichText, $locales, $translate);
    }

    /**
     * @param list<string> $locales
     * @param callable(TranslationKey, string): ?string $translate
     */
    private function resolveIn(mixed $value, bool $richText, array $locales, callable $translate): mixed
    {
        $key = TranslationKey::fromValue($value);
        if (null === $key) {
            return is_array($value) ? array_map(fn (mixed $inner): mixed => $this->resolveIn($inner, $richText, $locales, $translate), $value) : $value;
        }

        $translations = [];
        foreach ($locales as $locale) {
            $translation = $translate($key, $locale);
            if (null !== $translation && '' !== $translation) {
                $translations[$locale] = $richText ? $this->richText->fromHtml($translation) : $translation;
            }
        }

        return [] === $translations ? new \stdClass() : $translations;
    }
}
