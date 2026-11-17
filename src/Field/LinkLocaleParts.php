<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

/**
 * What a Link field of the default shape keeps per locale inside its one shared value: its label, and the ref of a
 * target whose ref is translatable, such as a URL. Those are translated like a translatable field's value, as one value
 * per locale: {label?: string, ref?: string}, with what the locale has. See "Links" in docs/spec/content-model.md and
 * "Translatable and shared fields" in docs/spec/localization.md
 *
 * @internal
 */
final class LinkLocaleParts
{
    private const PARTS = ['label', 'ref'];

    /**
     * Whether the field is a Link that keeps parts per locale, which a Link whose translatable is null does
     */
    public static function areKeptBy(Field $field): bool
    {
        return $field instanceof Link && null === $field->translatable;
    }

    /**
     * The parts the stored link has in each locale, leaving out the locales that have none
     *
     * @return array<string, array{label?: string, ref?: string}>
     */
    public static function byLocale(mixed $link): array
    {
        $link = self::normalized($link);
        if (!is_array($link)) {
            return [];
        }

        $byLocale = [];
        foreach (self::PARTS as $part) {
            $map = $link[$part] ?? null;
            if (!self::isLocaleMap($map)) {
                continue;
            }

            foreach ($map as $locale => $value) {
                if ('' !== $value) {
                    $byLocale[$locale][$part] = $value;
                }
            }
        }

        ksort($byLocale);

        return $byLocale;
    }

    /**
     * The stored link with the locale's parts replaced by the given ones: a part left out, or empty, is removed in the
     * locale
     *
     * @throws \InvalidArgumentException when there's no link to write into, the parts aren't {label?, ref?} strings, or
     *                                   a ref is given for a link whose ref every locale shares
     *
     * @return array<mixed>
     */
    public static function with(mixed $link, string $locale, mixed $parts): array
    {
        $link = self::normalized($link);
        if (!is_array($link)) {
            throw new \InvalidArgumentException('There\'s no link to write the label and URL of a locale into');
        }

        if (!is_array($parts) || [] !== array_diff(array_keys($parts), self::PARTS) || [] !== array_filter($parts, static fn (mixed $value): bool => !is_string($value))) {
            throw new \InvalidArgumentException('A link\'s parts in a locale are an object with a "label" and a "ref" string, each optional');
        }

        if (array_key_exists('ref', $parts) && !self::isLocaleMap($link['ref'] ?? null)) {
            throw new \InvalidArgumentException('The link\'s ref is shared by every locale, so it takes no ref per locale');
        }

        foreach (self::PARTS as $part) {
            // A link without a label has none in any locale
            $map = $link[$part] ?? ('label' === $part ? [] : null);
            if (!self::isLocaleMap($map)) {
                continue;
            }

            unset($map[$locale]);
            $value = $parts[$part] ?? '';
            if ('' !== $value) {
                $map[$locale] = $value;
            }

            $link[$part] = $map;
        }

        return $link;
    }

    /**
     * The stored link with its locale maps as arrays: canonical JSON decodes an empty map, such as a label in no locale,
     * as an empty stdClass
     */
    public static function normalized(mixed $link): mixed
    {
        if (!is_array($link)) {
            return $link;
        }

        foreach (self::PARTS as $part) {
            if (($link[$part] ?? null) instanceof \stdClass) {
                $link[$part] = get_object_vars($link[$part]);
            }
        }

        return $link;
    }

    /**
     * @phpstan-assert-if-true array<string, string> $value
     */
    public static function isLocaleMap(mixed $value): bool
    {
        if (!is_array($value) || ([] !== $value && array_is_list($value))) {
            return false;
        }

        foreach ($value as $locale => $localized) {
            if (!is_string($locale) || str_starts_with($locale, '$') || !is_string($localized)) {
                return false;
            }
        }

        return true;
    }
}
