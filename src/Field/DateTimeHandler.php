<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Handles DateTime fields: a date and time, always stored in UTC as "2026-11-13T09:30:00Z", to the second. A value with
 * another offset, or with fractions of a second, as JavaScript's toISOString() writes it, is saved in UTC
 */
final class DateTimeHandler implements FitCheckingFieldHandlerInterface, SanitizingFieldHandlerInterface
{
    public const FORMAT = 'Y-m-d\TH:i:s\Z';

    /** The ISO 8601 forms it saves in UTC: with an offset or Z, without and with milliseconds */
    private const ACCEPTED = ['!Y-m-d\TH:i:sP', '!Y-m-d\TH:i:s.vP'];

    public function schema(Field $field): array
    {
        return ['type' => 'string', 'format' => 'date-time'];
    }

    public function hydrate(Field $field, mixed $value, HydrationContext $context): ?\DateTimeImmutable
    {
        return self::parse($value);
    }

    public function fits(Field $field, mixed $value): bool
    {
        return null !== self::parse($value);
    }

    public function sanitize(Field $field, mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        foreach (self::ACCEPTED as $format) {
            $at = \DateTimeImmutable::createFromFormat($format, $value);
            if (false !== $at) {
                return $at->setTimezone(new \DateTimeZone('UTC'))->format(self::FORMAT);
            }
        }

        return $value;
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

        $context->buildViolation('setono_sylius_cms.field.not_date_time')->addViolation();
    }

    /**
     * A time that exists, written in UTC exactly as the stored format writes it, or null
     */
    private static function parse(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value)) {
            return null;
        }

        $at = \DateTimeImmutable::createFromFormat('!' . self::FORMAT, $value, new \DateTimeZone('UTC'));

        return false !== $at && $at->format(self::FORMAT) === $value ? $at : null;
    }
}
