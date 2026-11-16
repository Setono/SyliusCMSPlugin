<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Json;

/**
 * The `type` keyword of a JSON Schema, checked against a value in the shape CanonicalJson::decode() gives: a JSON object
 * is an array with keys, and a JSON array a list. It's what a value of a field type must at least be, for a field type
 * whose handler doesn't check values itself
 *
 * @internal
 */
final class SchemaType
{
    /**
     * @param array<array-key, mixed> $schema
     *
     * @return bool whether the value's JSON type is one the schema's type names, or true when it names none
     */
    public static function allows(array $schema, mixed $value): bool
    {
        if (!isset($schema['type'])) {
            return true;
        }

        foreach ((array) $schema['type'] as $type) {
            if (self::is($type, $value)) {
                return true;
            }
        }

        return false;
    }

    private static function is(mixed $type, mixed $value): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'number' => is_int($value) || is_float($value),
            'integer' => is_int($value),
            'boolean' => is_bool($value),
            'null' => null === $value,
            'array' => is_array($value) && array_is_list($value),
            'object' => is_array($value) && !array_is_list($value),
            default => false,
        };
    }
}
