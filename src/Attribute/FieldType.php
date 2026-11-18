<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Attribute;

use Setono\SyliusCMSPlugin\Binding\BindingType;

/**
 * Declares a field type on its field attribute class: what the plugin needs before it stores or shows a value.
 * See "Adding a field type" in docs/spec/extending.md
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class FieldType
{
    /**
     * @param string $code the field type's code; unique, and never changes once content uses it
     * @param bool $translatable its default translatability, used when a field leaves "translatable" at null
     * @param list<BindingType> $bindable the binding types a field of this type accepts. Empty means it can't be bound
     * @param string|null $control the tag name of its field control, the custom element editors enter a value with,
     *                            for a project's own field type. See "Field controls" in docs/spec/extending.md
     */
    public function __construct(
        public string $code,
        public bool $translatable = false,
        public array $bindable = [],
        public ?string $control = null,
    ) {
    }
}
