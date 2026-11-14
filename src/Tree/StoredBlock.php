<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tree;

/**
 * A block in the stored block tree format. See "A block" in docs/spec/content-model.md
 */
final class StoredBlock
{
    /**
     * @param array<string, mixed> $fields field name => value; a translatable field's value is a map of locale code => value
     * @param array<string, mixed> $translated field name => locale code => translation state
     * @param list<array<string, mixed>> $conditions
     * @param list<StoredBlock> $children
     */
    public function __construct(
        public readonly string $key,
        public readonly string $type,
        public int $version,
        public ?string $style = null,
        public array $fields = [],
        public array $translated = [],
        public array $conditions = [],
        public array $children = [],
    ) {
    }

    /**
     * A clone is a deep copy, so changing it leaves the original as it is
     */
    public function __clone()
    {
        $this->children = array_map(static fn (StoredBlock $child): StoredBlock => clone $child, $this->children);
    }

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

        $values = $this->fields[$field] ?? [];
        if (!is_array($values)) {
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
     * @return list<string> the keys of this block and of every block below it
     */
    public function keys(): array
    {
        $keys = [$this->key];
        foreach ($this->children as $child) {
            $keys = [...$keys, ...$child->keys()];
        }

        return $keys;
    }
}
