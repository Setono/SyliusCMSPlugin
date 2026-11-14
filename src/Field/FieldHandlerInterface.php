<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * The service half of a field type. See "Adding a field type" in docs/spec/extending.md
 */
interface FieldHandlerInterface
{
    /**
     * JSON Schema 2020-12 for one value (one locale's value, for a translatable field), plus x-cms-* keywords
     *
     * @return array<string, mixed>
     */
    public function schema(Field $field): array;

    /**
     * Turns a stored value into what templates get. Null means the value is missing,
     * which is also what a stored value that doesn't fit the field type counts as
     */
    public function hydrate(Field $field, mixed $value, HydrationContext $context): mixed;

    /**
     * @return iterable<Reference>
     */
    public function references(Field $field, mixed $value): iterable;

    public function validate(Field $field, mixed $value, ExecutionContextInterface $context): void;
}
