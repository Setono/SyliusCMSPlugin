<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Binding;

use Setono\SyliusCMSPlugin\Registry\FieldMetadata;

/**
 * The binding a field stores as its whole value, which only a field that binds its whole value can
 * (FieldMetadata::bindsWholeValue()). Its fallback is what the field would otherwise store. See "Fields" and "Field
 * values" in docs/spec/content-model.md
 *
 * @internal
 */
final class BoundValue
{
    private function __construct()
    {
    }

    /**
     * Whether the field stores a binding as its whole value, whether or not it fits the shape
     */
    public static function isBound(FieldMetadata $field, mixed $stored): bool
    {
        return $field->bindsWholeValue() && StoredBinding::isBinding($stored);
    }

    /**
     * The binding the field stores as its whole value, or null when it stores none, or one of another shape
     */
    public static function of(FieldMetadata $field, mixed $stored): ?StoredBinding
    {
        return $field->bindsWholeValue() ? StoredBinding::fromValue($stored) : null;
    }
}
