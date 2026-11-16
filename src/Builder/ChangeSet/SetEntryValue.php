<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\ChangeSet;

use Setono\SyliusCMSPlugin\Json\ListedObject;
use Setono\SyliusCMSPlugin\Tree\StoredTreeSerializer;

/**
 * Sets a value of one of the entry's own fields, such as a translation's title. Setting a value in a locale the entry has
 * no translation in adds the translation
 */
final readonly class SetEntryValue implements Change
{
    public const OP = 'set_entry_value';

    /**
     * @param string|null $locale the locale for a translatable field, null for a shared one
     * @param mixed $value null removes the value
     */
    public function __construct(
        public string $field,
        public ?string $locale,
        public mixed $value,
    ) {
    }

    public function toArray(StoredTreeSerializer $serializer): ListedObject
    {
        return new ListedObject([
            'op' => self::OP,
            'field' => $this->field,
            'locale' => $this->locale,
            'value' => $this->value,
        ]);
    }
}
