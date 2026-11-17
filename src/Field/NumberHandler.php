<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Handles Number fields: a number, optionally whole, between bounds and on steps
 */
final class NumberHandler implements FitCheckingFieldHandlerInterface
{
    /**
     * How far a float may be off a step and still be on it, since 0.1 + 0.2 isn't quite 0.3
     */
    private const TOLERANCE = 1e-9;

    public function schema(Field $field): array
    {
        $field = self::number($field);

        $schema = ['type' => $field->integer ? 'integer' : 'number'];
        if (null !== $field->min) {
            $schema['minimum'] = $field->min;
        }

        if (null !== $field->max) {
            $schema['maximum'] = $field->max;
        }

        $schema['x-cms-options'] = ['min' => $field->min, 'max' => $field->max, 'step' => $field->step, 'integer' => $field->integer];

        return $schema;
    }

    public function hydrate(Field $field, mixed $value, HydrationContext $context): int|float|null
    {
        $field = self::number($field);

        if ((!is_int($value) && !is_float($value)) || !$this->fits($field, $value)) {
            return null;
        }

        return $field->integer ? (int) $value : $value;
    }

    /**
     * A number fits, even one out of the bounds or off the steps, which still renders and which publish refuses. In a
     * field that takes whole numbers, only a whole number fits
     */
    public function fits(Field $field, mixed $value): bool
    {
        $field = self::number($field);

        if (is_int($value)) {
            return true;
        }

        if (!is_float($value) || !is_finite($value)) {
            return false;
        }

        return !$field->integer || self::isWhole($value);
    }

    public function references(Field $field, mixed $value): iterable
    {
        return [];
    }

    public function validate(Field $field, mixed $value, ExecutionContextInterface $context): void
    {
        $field = self::number($field);

        if (null === $value) {
            return;
        }

        if (!is_int($value) && (!is_float($value) || !is_finite($value))) {
            $context->buildViolation('setono_sylius_cms.field.not_number')->addViolation();

            return;
        }

        if ($field->integer && is_float($value) && !self::isWhole($value)) {
            $context->buildViolation('setono_sylius_cms.field.not_whole_number')->addViolation();

            return;
        }

        if (null !== $field->min && $value < $field->min) {
            $context->buildViolation('setono_sylius_cms.field.too_small')
                ->setParameter('{{ limit }}', self::format($field->min))
                ->addViolation()
            ;

            return;
        }

        if (null !== $field->max && $value > $field->max) {
            $context->buildViolation('setono_sylius_cms.field.too_large')
                ->setParameter('{{ limit }}', self::format($field->max))
                ->addViolation()
            ;

            return;
        }

        if (null === $field->step) {
            return;
        }

        // Steps count from the lowest value, as in an HTML number input
        $base = $field->min ?? 0;
        $steps = ($value - $base) / $field->step;
        if (abs($steps - round($steps)) <= self::TOLERANCE) {
            return;
        }

        $lower = $base + floor($steps) * $field->step;
        $context->buildViolation('setono_sylius_cms.field.not_on_step')
            ->setParameter('{{ lower }}', self::format($lower))
            ->setParameter('{{ upper }}', self::format($lower + $field->step))
            ->addViolation()
        ;
    }

    private static function isWhole(float $value): bool
    {
        return floor($value) === $value && abs($value) <= \PHP_INT_MAX;
    }

    /**
     * A number for a message, without float noise such as 0.30000000000000004
     */
    private static function format(int|float $number): string
    {
        return rtrim(rtrim(sprintf('%.10F', round($number, 10)), '0'), '.');
    }

    private static function number(Field $field): Number
    {
        if (!$field instanceof Number) {
            throw new \InvalidArgumentException(sprintf('%s handles %s fields, got %s', self::class, Number::class, $field::class));
        }

        return $field;
    }
}
