<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Setono\SyliusCMSPlugin\Attribute\FieldType;

/**
 * A date and time, always stored in UTC as "2026-11-13T09:30:00Z", to the second, and hydrated as ?DateTimeImmutable
 * in UTC
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
#[FieldType(code: 'date_time')]
final class DateTime extends Field
{
}
