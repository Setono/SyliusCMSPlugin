<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Binding;

use Setono\SyliusCMSPlugin\Registry\Code;

/**
 * What a bound field stores in place of a value: {"$bind": "<context key>.<property code>", "$params": {…},
 * "$fallback": …}. The parameters are shared field values of the bindable property's parameters class, and the fallback
 * is what the field would otherwise store: a value, a locale map of values for a translatable field, or null. A Link
 * holds one in its ref. See "Field values" in docs/spec/content-model.md
 *
 * @internal
 */
final readonly class StoredBinding
{
    public const BIND = '$bind';

    public const PARAMS = '$params';

    public const FALLBACK = '$fallback';

    /**
     * @param string $property "<context key>.<property code>", such as product.name
     * @param array<string, mixed> $params the stored parameter values, by field name
     * @param mixed $fallback what the field would otherwise store, or null
     */
    public function __construct(
        public string $property,
        public array $params = [],
        public mixed $fallback = null,
    ) {
    }

    /**
     * Whether the value is meant as a binding, as a map with a "$bind" key, whether or not it fits the shape
     */
    public static function isBinding(mixed $value): bool
    {
        return is_array($value) && array_key_exists(self::BIND, $value);
    }

    /**
     * The binding the value holds, or null when it isn't one, or doesn't fit the shape. Canonical JSON decodes
     * parameters or a fallback stored as an empty map as a stdClass
     */
    public static function fromValue(mixed $value): ?self
    {
        if (!is_array($value) || !self::isBinding($value)) {
            return null;
        }

        $keys = array_keys($value);
        sort($keys);
        if ([self::BIND, self::FALLBACK, self::PARAMS] !== $keys) {
            return null;
        }

        $property = $value[self::BIND];
        $params = $value[self::PARAMS] instanceof \stdClass ? get_object_vars($value[self::PARAMS]) : $value[self::PARAMS];
        $fallback = $value[self::FALLBACK] instanceof \stdClass ? get_object_vars($value[self::FALLBACK]) : $value[self::FALLBACK];

        if (!is_string($property) || !self::isProperty($property) || !is_array($params) || ([] !== $params && array_is_list($params)) || self::isBinding($fallback)) {
            return null;
        }

        /** @var array<string, mixed> $map */
        $map = $params;

        return new self($property, $map, $fallback);
    }

    /**
     * Whether the string names a bindable property the way a binding does: "<context key>.<property code>"
     */
    public static function isProperty(string $property): bool
    {
        $parts = explode('.', $property);

        return 2 === count($parts) && Code::isValid($parts[0]) && Code::isValid($parts[1]);
    }

    /**
     * The context key, channel or customer the property is read from
     */
    public function contextKey(): string
    {
        return explode('.', $this->property, 2)[0];
    }

    /**
     * The property's code
     */
    public function code(): string
    {
        return explode('.', $this->property, 2)[1] ?? '';
    }

    /**
     * The binding as it's stored, with no parameters as an empty map, so it stays a map in JSON
     *
     * @return array{'$bind': string, '$params': array<string, mixed>|\stdClass, '$fallback': mixed}
     */
    public function toArray(): array
    {
        return [self::BIND => $this->property, self::PARAMS => [] === $this->params ? new \stdClass() : $this->params, self::FALLBACK => $this->fallback];
    }
}
