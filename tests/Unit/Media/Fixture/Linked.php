<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Media\Fixture;

use Setono\SyliusCMSPlugin\Attribute\FieldType;
use Setono\SyliusCMSPlugin\Field\Field;

/**
 * A field type whose values hold references of several types, as rich text with links does: a list of
 * {type, id} pairs
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
#[FieldType(code: 'app_linked', translatable: true)]
final class Linked extends Field
{
}
