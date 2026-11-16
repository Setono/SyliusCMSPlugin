<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\ChangeSet;

use Setono\SyliusCMSPlugin\Json\ListedObject;
use Setono\SyliusCMSPlugin\Tree\StoredTreeSerializer;

/**
 * Sets a value of one of the layout's fields. It names the layout it was made in, so a value made in a layout a colleague
 * has switched away from since isn't set in the new one
 */
final readonly class SetLayoutValue implements Change
{
    public const OP = 'set_layout_value';

    /**
     * @param string $layout the code of the layout the value was set in
     * @param string|null $locale the locale for a translatable field, null for a shared one
     * @param mixed $value null removes the value
     */
    public function __construct(
        public string $layout,
        public string $field,
        public ?string $locale,
        public mixed $value,
    ) {
    }

    public function toArray(StoredTreeSerializer $serializer): ListedObject
    {
        return new ListedObject([
            'op' => self::OP,
            'layout' => $this->layout,
            'field' => $this->field,
            'locale' => $this->locale,
            'value' => $this->value,
        ]);
    }
}
