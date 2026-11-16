<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

/**
 * A field type handler that tells whether a stored value fits its field type, such as a string in a Text field. A value
 * that doesn't fit counts as missing, and the admin flags it and setono:cms:lint reports it ("Values that don't fit" in
 * docs/spec/content-model.md). For a handler without it, a value fits when its JSON type is the one its schema names.
 * The spec's FieldHandlerInterface has no such method, so this stays internal for now
 *
 * @internal
 */
interface FitCheckingFieldHandlerInterface extends FieldHandlerInterface
{
    /**
     * @param mixed $value one value that isn't missing, which for a translatable field is one locale's value
     */
    public function fits(Field $field, mixed $value): bool;
}
