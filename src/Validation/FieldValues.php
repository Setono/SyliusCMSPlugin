<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Validation;

/**
 * The values of a block's fields as publish checks them together, against the constraints on its block type's class,
 * such as ExactlyOneOf: the value each field has in one locale, as stored, leaving out the fields whose value is
 * missing there. A translatable field has the value that renders in the locale, its own or its fallback chain's.
 * See "Validation" in docs/spec/publishing.md
 */
final readonly class FieldValues
{
    /**
     * @param array<string, mixed> $values the value of each field that isn't missing, as stored, by field name
     * @param array<string, string> $labels each field's label, translated, by field name
     * @param string|null $locale the locale of the translatable fields' values, or null when the block type has none
     * @param list<string> $fallbackLocales the locale and the locales it falls back through, in order, which a value kept
     *                                      per locale inside a shared one, such as a link's label, is read along
     */
    public function __construct(
        private array $values,
        private array $labels = [],
        public ?string $locale = null,
        public array $fallbackLocales = [],
    ) {
    }

    /**
     * Whether the field has a value, one that isn't missing
     */
    public function has(string $field): bool
    {
        return array_key_exists($field, $this->values);
    }

    /**
     * The field's value as stored, or null when it's missing
     */
    public function get(string $field): mixed
    {
        return $this->values[$field] ?? null;
    }

    /**
     * The field's label, translated, to name it in a message
     */
    public function label(string $field): string
    {
        return $this->labels[$field] ?? $field;
    }
}
