<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Localization;

use Setono\SyliusCMSPlugin\Field\EmptinessCheckingFieldHandlerInterface;
use Setono\SyliusCMSPlugin\Field\Field;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * The one rule for which field values are missing, and the values a translatable field falls back through past them,
 * whether it's a block's, a layout's or the entry's own. Rendering and validation both read values through it, so what
 * validation calls missing is what rendering falls back past. See "Fallback chain" in docs/spec/localization.md
 *
 * @internal
 */
final readonly class FallbackValues
{
    public function __construct(
        private FieldHandlerLocator $fieldHandlers,
        private FallbackChainInterface $fallbackChain,
    ) {
    }

    /**
     * Null, '' and [] are missing, and so is what the field type handler counts as empty, such as an empty rich text
     * document
     *
     * @param mixed $value one value, which for a translatable field is one locale's value
     */
    public function isMissing(Field $field, mixed $value): bool
    {
        if (null === $value || '' === $value || [] === $value) {
            return true;
        }

        $handler = $this->fieldHandlers->get($field);

        return $handler instanceof EmptinessCheckingFieldHandlerInterface && $handler->isEmpty($field, $value);
    }

    /**
     * The values of a translatable field that aren't missing, in the order the locale's fallback chain reaches them
     *
     * @param mixed $stored what the field stores: a map from locale code to value
     *
     * @return \Generator<string, mixed> locale code => value
     */
    public function inFallbackOrder(Field $field, mixed $stored, string $localeCode): \Generator
    {
        if (!is_array($stored)) {
            return;
        }

        foreach ($this->fallbackChain->localesFor($localeCode) as $locale) {
            $value = $stored[$locale] ?? null;
            if (!$this->isMissing($field, $value)) {
                yield $locale => $value;
            }
        }
    }

    /**
     * The value of a translatable entry field, such as the title, in the locale: the first one the entry's translations
     * hold that isn't missing, in the order the locale's fallback chain reaches them. Null when none holds one.
     * Slugs never fall back, so a URL exists only in locales with a slug: read it from the locale's own translation
     *
     * @throws \InvalidArgumentException for the slug, or a field the translations don't have
     */
    public function entryValue(EntryInterface $entry, string $field, string $localeCode): mixed
    {
        if ('slug' === $field) {
            throw new \InvalidArgumentException('Slugs never fall back. Read the slug from the translation in the locale');
        }

        $translations = $entry->getTranslations();
        foreach ($this->fallbackChain->localesFor($localeCode) as $locale) {
            $translation = $translations->get($locale);
            if (null === $translation) {
                continue;
            }

            if (!property_exists($translation, $field)) {
                throw new \InvalidArgumentException(sprintf('The translations of %s have no field "%s"', $entry::class, $field));
            }

            $value = (new \ReflectionProperty($translation, $field))->getValue($translation);
            if (null !== $value && '' !== $value && [] !== $value) {
                return $value;
            }
        }

        return null;
    }
}
