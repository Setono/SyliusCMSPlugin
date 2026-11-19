<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Source;

/**
 * What a list field stores when a source fills it: {"$source": code, "$params": {…}, "$limit": n}. The parameters are
 * shared field values of the source's parameters class, and the limit is how many items show, or the page size of a
 * list that paginates. See "Field values" in docs/spec/content-model.md
 *
 * @internal
 */
final readonly class StoredSource
{
    public const SOURCE = '$source';

    public const PARAMS = '$params';

    public const LIMIT = '$limit';

    /**
     * @param array<array-key, mixed> $params the stored parameter values, by field name
     * @param int<1, max> $limit
     */
    public function __construct(
        public string $code,
        public array $params,
        public int $limit,
    ) {
    }

    /**
     * The field values with each Source value's empty parameters as an empty map, {} in canonical JSON, as the format has
     * them, however they were read, such as from the builder's JSON decoded as arrays. A translatable field's value is
     * a map of locales to values, so a source in a locale's value is one too. Every other value is left as it is
     *
     * @param array<array-key, mixed> $fields
     *
     * @return array<array-key, mixed>
     */
    public static function canonicalFields(array $fields): array
    {
        return array_map(static fn (mixed $value): mixed => self::isSource($value) ? self::canonical($value) : (is_array($value) && !array_is_list($value) ? array_map(self::canonical(...), $value) : $value), $fields);
    }

    /**
     * Whether the value is meant as a source, as a map with a "$source" key, whether or not it fits the shape
     */
    public static function isSource(mixed $value): bool
    {
        return is_array($value) && array_key_exists(self::SOURCE, $value);
    }

    private static function canonical(mixed $value): mixed
    {
        if (!self::isSource($value) || !is_array($value) || [] !== ($value[self::PARAMS] ?? null)) {
            return $value;
        }

        $value[self::PARAMS] = new \stdClass();

        return $value;
    }

    /**
     * The source the value holds, or null when it isn't one, or doesn't fit the shape. Canonical JSON decodes parameters
     * stored as an empty map as a stdClass
     */
    public static function fromValue(mixed $value): ?self
    {
        if (!is_array($value) || !self::isSource($value) || [self::LIMIT, self::PARAMS, self::SOURCE] !== self::sortedKeys($value)) {
            return null;
        }

        $code = $value[self::SOURCE];
        $params = $value[self::PARAMS];
        $limit = $value[self::LIMIT];
        if ($params instanceof \stdClass) {
            $params = get_object_vars($params);
        }

        if (!is_string($code) || '' === $code || !is_int($limit) || $limit < 1 || !is_array($params) || ([] !== $params && array_is_list($params))) {
            return null;
        }

        return new self($code, $params, $limit);
    }

    /**
     * @param array<array-key, mixed> $value
     *
     * @return list<array-key>
     */
    private static function sortedKeys(array $value): array
    {
        $keys = array_keys($value);
        sort($keys);

        return $keys;
    }
}
