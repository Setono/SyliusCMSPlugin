<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

use Setono\SyliusCMSPlugin\Attribute\FieldType;
use Setono\SyliusCMSPlugin\Field\Field;

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
}
