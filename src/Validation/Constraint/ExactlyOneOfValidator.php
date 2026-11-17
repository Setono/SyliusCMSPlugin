<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Validation\Constraint;

use Setono\SyliusCMSPlugin\Validation\FieldValues;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class ExactlyOneOfValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ExactlyOneOf) {
            throw new UnexpectedTypeException($constraint, ExactlyOneOf::class);
        }

        if (!$value instanceof FieldValues) {
            throw new UnexpectedValueException($value, FieldValues::class);
        }

        if (1 === count(array_filter($constraint->fields, $value->has(...)))) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('%fields%', implode(', ', array_map($value->label(...), $constraint->fields)))
            ->addViolation()
        ;
    }
}
