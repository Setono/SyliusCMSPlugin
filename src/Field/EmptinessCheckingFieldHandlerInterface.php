<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

/**
 * A field type handler whose values can be there and still hold nothing, such as an empty rich text document, which
 * counts as missing. Validation reads it to tell whether a required field has a value without hydrating it, because
 * hydrating needs a render context. The spec's FieldHandlerInterface has no such method, so this stays internal for now
 *
 * @internal
 */
interface EmptinessCheckingFieldHandlerInterface extends FieldHandlerInterface
{
    /**
     * @param mixed $value one value, which for a translatable field is one locale's value. Never null, '' or [],
     *                     which are missing anyway
     */
    public function isEmpty(Field $field, mixed $value): bool;
}
