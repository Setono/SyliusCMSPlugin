<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Binding;

use Setono\SyliusCMSPlugin\Field\InnerBindingFieldHandlerInterface;
use Setono\SyliusCMSPlugin\Registry\FieldMetadata;

/**
 * Which fields show a binding as their whole value: those whose field type accepts binding types, such as Text,
 * Textarea and Number, unless its handler holds its bindings inside its values, as Link and rich text do. Such a
 * field's binding takes the place of its value, or of its locale map when it's translatable, and its fallback is what
 * it would otherwise store. See "Fields" and "Field values" in docs/spec/content-model.md
 *
 * @internal
 */
final class BoundValue
{
    private function __construct()
    {
    }

    /**
     * Whether a binding can be the field's whole value
     */
    public static function isBindable(FieldMetadata $field): bool
    {
        return [] !== $field->type->bindable && !is_a($field->handler(), InnerBindingFieldHandlerInterface::class, true);
    }

    /**
     * Whether the field stores a binding as its whole value, whether or not it fits the shape
     */
    public static function isBound(FieldMetadata $field, mixed $stored): bool
    {
        return self::isBindable($field) && StoredBinding::isBinding($stored);
    }

    /**
     * The binding the field stores as its whole value, or null when it stores none, or one of another shape
     */
    public static function of(FieldMetadata $field, mixed $stored): ?StoredBinding
    {
        return self::isBindable($field) ? StoredBinding::fromValue($stored) : null;
    }
}
