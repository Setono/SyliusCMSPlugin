<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Validation\Constraint;

use Setono\SyliusCMSPlugin\Validation\FieldValues;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class EndsAfterStartValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof EndsAfterStart) {
            throw new UnexpectedTypeException($constraint, EndsAfterStart::class);
        }

        if (!$value instanceof FieldValues) {
            throw new UnexpectedValueException($value, FieldValues::class);
        }

        $start = self::date($value->get($constraint->start));
        $end = self::date($value->get($constraint->end));
        if (null === $start || null === $end || $start < $end) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->atPath($constraint->end)
            ->setParameter('%start%', $value->label($constraint->start))
            ->setParameter('%end%', $value->label($constraint->end))
            ->addViolation()
        ;
    }

    /**
     * A stored Date or Date and time value, or null for a missing one, or one that's no date
     */
    private static function date(mixed $value): ?\DateTimeImmutable
    {
        $date = is_string($value) ? date_create_immutable($value) : false;

        return false === $date ? null : $date;
    }
}
