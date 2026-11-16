<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Builder\Fixture;

use Setono\SyliusCMSPlugin\Attribute\FieldType;
use Setono\SyliusCMSPlugin\Field\Field;

/**
 * A project's own field type, which brings its own field control
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
#[FieldType(code: 'app_opening_hours', control: 'app-opening-hours')]
final class OpeningHours extends Field
{
}
