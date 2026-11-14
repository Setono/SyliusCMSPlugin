<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Json;

/**
 * Writes JSON the one way the plugin writes it everywhere: the tree column, drafts, revisions, presets,
 * exports and fingerprints. See "Canonical JSON" in docs/spec/content-model.md.
 *
 * In the values it encodes:
 * - a ListedObject is an object whose keys keep the order given
 * - a stdClass or an array that isn't a list is a map, whose keys are sorted by byte value
 * - an array that is a list is a JSON array
 *
 * An empty map is written as {} only when it's a stdClass or an empty ListedObject, because an empty PHP array is a list
 */
final class CanonicalJson
{
    public const FLAGS = \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION | \JSON_THROW_ON_ERROR;

    public static function encode(mixed $value): string
    {
        return json_encode(self::normalize($value), self::FLAGS);
    }

    /**
     * Files (presets and exports) are pretty-printed with four spaces and end with a newline
     */
    public static function encodeFile(mixed $value): string
    {
        return json_encode(self::normalize($value), self::FLAGS | \JSON_PRETTY_PRINT) . "\n";
    }

    /**
     * Decodes JSON into arrays, keeping empty objects as an empty stdClass, so encoding the result
     * gives the same JSON back
     *
     * @throws \JsonException
     */
    public static function decode(string $json): mixed
    {
        return self::denormalize(json_decode($json, flags: \JSON_THROW_ON_ERROR));
    }

    private static function normalize(mixed $value): mixed
    {
        if ($value instanceof ListedObject) {
            return (object) array_map(self::normalize(...), $value->properties);
        }

        if ($value instanceof \stdClass) {
            return self::normalizeMap(get_object_vars($value));
        }

        if (is_array($value)) {
            return array_is_list($value) ? array_map(self::normalize(...), $value) : self::normalizeMap($value);
        }

        if (null === $value || is_scalar($value)) {
            return $value;
        }

        throw new \InvalidArgumentException(sprintf('Canonical JSON can only hold arrays, scalars, null, stdClass and %s, got %s', ListedObject::class, get_debug_type($value)));
    }

    /**
     * @param array<array-key, mixed> $map
     */
    private static function normalizeMap(array $map): \stdClass
    {
        ksort($map, \SORT_STRING);

        return (object) array_map(self::normalize(...), $map);
    }

    private static function denormalize(mixed $value): mixed
    {
        if ($value instanceof \stdClass) {
            $properties = get_object_vars($value);

            return [] === $properties ? $value : array_map(self::denormalize(...), $properties);
        }

        if (is_array($value)) {
            return array_map(self::denormalize(...), $value);
        }

        return $value;
    }
}
