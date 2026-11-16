<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Setono\SyliusCMSPlugin\Attribute\FieldType;

/**
 * A calendar day, stored as "2026-11-13" and hydrated as ?DateTimeImmutable at midnight UTC
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
#[FieldType(code: 'date')]
final class Date extends Field
{
}
