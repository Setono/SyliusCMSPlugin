<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

/**
 * A field type handler that cleans a value up before it's saved, such as rich text's, which drops what its schema
 * doesn't have. The spec's FieldHandlerInterface has no such method, so this stays internal for now
 *
 * @internal
 */
interface SanitizingFieldHandlerInterface extends FieldHandlerInterface
{
    /**
     * @param mixed $value one value, which for a translatable field is one locale's value
     *
     * @return mixed what's saved in its place
     */
    public function sanitize(Field $field, mixed $value): mixed;
}
