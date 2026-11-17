<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

use Setono\SyliusCMSPlugin\Attribute\FieldType;
use Setono\SyliusCMSPlugin\Field\Field;
use Setono\SyliusCMSPlugin\Field\InnerBindingFieldHandlerInterface;

/**
 * One field of a block type, a layout or a content type: a typed property with a field attribute
 */
final readonly class FieldMetadata
{
    /**
     * @param string $name the property name, which is also the key its values are stored under
     * @param bool $translatable resolved from the field, or else the field type's default
     * @param mixed $default the property's default value
     */
    public function __construct(
        public string $name,
        public Field $field,
        public FieldType $type,
        public bool $translatable,
        public mixed $default,
    ) {
    }

    /**
     * The handler's service id
     */
    public function handler(): string
    {
        return $this->field->handledBy();
    }

    /**
     * Whether a binding can be its whole value: its field type accepts binding types, such as Text, Textarea and
     * Number, and its handler doesn't hold its bindings inside its values. Such a field's binding takes the place of its
     * value, or of its locale map when it's translatable. See "Fields" in docs/spec/content-model.md
     */
    public function bindsWholeValue(): bool
    {
        return [] !== $this->type->bindable && !$this->bindsInside();
    }

    /**
     * Whether its handler holds its bindings inside its values, as a Link's ref and rich text's tokens do, so a binding
     * is never its whole value
     */
    public function bindsInside(): bool
    {
        return [] !== $this->type->bindable && is_a($this->handler(), InnerBindingFieldHandlerInterface::class, true);
    }
}
