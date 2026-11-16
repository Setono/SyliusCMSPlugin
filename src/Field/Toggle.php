<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Setono\SyliusCMSPlugin\Attribute\FieldType;

/**
 * On or off, stored as a boolean and hydrated as bool
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
#[FieldType(code: 'toggle')]
final class Toggle extends Field
{
}
