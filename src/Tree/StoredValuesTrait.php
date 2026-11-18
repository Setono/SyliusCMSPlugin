<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tree;

use Setono\SyliusCMSPlugin\Binding\StoredBinding;

/**
 * Writes the field values and translation state of a block or a layout, which store them the same way: a shared
 * field's one value, a translatable field's value by locale, and where each translated value came from.
 * See "Field values" and "Translation state" in docs/spec/content-model.md
 *
 * @property array<string, mixed> $fields field name => value; a translatable field's value is a map of locale code => value
 * @property array<string, mixed> $translated field name => locale code => translation state
 */
trait StoredValuesTrait
{
    /**
     * Sets a field's value, for one locale when $localeCode is given. A null or empty string value removes it,
     * because a field with no value is left out
     */
    public function setValue(string $field, ?string $localeCode, mixed $value): void
    {
        $isEmpty = null === $value || '' === $value;

        if (null === $localeCode) {
            if ($isEmpty) {
                unset($this->fields[$field]);
            } else {
                $this->fields[$field] = $value;
            }

            return;
        }

        // A locale's value replaces a binding, which is the whole field's
        $values = $this->fields[$field] ?? [];
        if (!is_array($values) || StoredBinding::isBinding($values)) {
            $values = [];
        }

        if ($isEmpty) {
            unset($values[$localeCode]);
        } else {
            $values[$localeCode] = $value;
        }

        if ([] === $values) {
            unset($this->fields[$field]);
        } else {
            $this->fields[$field] = $values;
        }
    }

    /**
     * Sets where a translatable field's value in a locale was translated from, or that it has no source when $source is null
     *
     * @param array{from: string, fingerprint: string}|null $source
     */
    public function setTranslated(string $field, string $localeCode, ?array $source): void
    {
        $locales = $this->translated[$field] ?? [];
        if (!is_array($locales)) {
            $locales = [];
        }

        if (null === $source) {
            unset($locales[$localeCode]);
        } else {
            $locales[$localeCode] = $source;
        }

        if ([] === $locales) {
            unset($this->translated[$field]);
        } else {
            $this->translated[$field] = $locales;
        }
    }
}
