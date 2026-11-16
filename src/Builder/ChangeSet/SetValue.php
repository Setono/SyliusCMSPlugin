<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\ChangeSet;

use Setono\SyliusCMSPlugin\Json\ListedObject;
use Setono\SyliusCMSPlugin\Tree\StoredTreeSerializer;

final readonly class SetValue implements Change
{
    public const OP = 'set_value';

    /**
     * @param string $key the block's key
     * @param string|null $locale the locale for a translatable field, null for a shared one
     * @param mixed $value null removes the value
     */
    public function __construct(
        public string $key,
        public string $field,
        public ?string $locale,
        public mixed $value,
    ) {
    }

    public function toArray(StoredTreeSerializer $serializer): ListedObject
    {
        return new ListedObject([
            'op' => self::OP,
            'key' => $this->key,
            'field' => $this->field,
            'locale' => $this->locale,
            'value' => $this->value,
        ]);
    }
}
