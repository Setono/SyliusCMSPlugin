<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Handles Date fields: a calendar day, stored as "2026-11-13"
 */
final class DateHandler implements FitCheckingFieldHandlerInterface
{
    public const FORMAT = 'Y-m-d';

    public function schema(Field $field): array
    {
        return ['type' => 'string', 'format' => 'date'];
    }

    /**
     * The day at midnight UTC
     */
    public function hydrate(Field $field, mixed $value, HydrationContext $context): ?\DateTimeImmutable
    {
        return self::parse($value);
    }

    public function fits(Field $field, mixed $value): bool
    {
        return null !== self::parse($value);
    }

    public function references(Field $field, mixed $value): iterable
    {
        return [];
    }

    public function validate(Field $field, mixed $value, ExecutionContextInterface $context): void
    {
        if (null === $value || null !== self::parse($value)) {
            return;
        }

        $context->buildViolation('setono_sylius_cms.field.not_date')->addViolation();
    }

    /**
     * A day that exists, written exactly as the stored format writes it, or null
     */
    private static function parse(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!' . self::FORMAT, $value, new \DateTimeZone('UTC'));

        return false !== $date && $date->format(self::FORMAT) === $value ? $date : null;
    }
}
