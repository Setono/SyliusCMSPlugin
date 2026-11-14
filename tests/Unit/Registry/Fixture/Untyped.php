<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture;

use Setono\SyliusCMSPlugin\Field\Field;

/**
 * A field attribute without #[FieldType]
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Untyped extends Field
{
}
