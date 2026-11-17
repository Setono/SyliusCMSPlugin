<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Validation;

use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Registry\FieldMetadata;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * A field's own constraints on a value it holds: its field type handler's validate(), and when the handler accepts the
 * value, the Symfony Validator constraints on its property. Publish checks a stored value with them, and hydration a
 * bound value, which shows the binding's fallback when it fails them
 *
 * @internal
 */
final readonly class FieldConstraints
{
    public function __construct(
        private FieldHandlerLocator $fieldHandlers,
        private ValidatorInterface $validator,
    ) {
    }

    /**
     * @param class-string $class the class whose property declares the field, and carries its constraints
     * @param mixed $value a value that isn't missing
     */
    public function validate(string $class, FieldMetadata $field, mixed $value): ConstraintViolationListInterface
    {
        $handler = $this->fieldHandlers->get($field->field);
        $violations = $this->validator->validate($value, new Callback(
            static fn (mixed $value, ExecutionContextInterface $context) => $handler->validate($field->field, $value, $context),
        ));

        if (0 === count($violations)) {
            $violations = $this->validator->validatePropertyValue($class, $field->name, $value);
        }

        return $violations;
    }

    /**
     * Whether the value passes the field's own constraints
     *
     * @param class-string $class the class whose property declares the field, and carries its constraints
     */
    public function accept(string $class, FieldMetadata $field, mixed $value): bool
    {
        return 0 === count($this->validate($class, $field, $value));
    }
}
