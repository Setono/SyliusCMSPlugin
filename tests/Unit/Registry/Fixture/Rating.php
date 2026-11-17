<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture;

use Setono\SyliusCMSPlugin\Attribute\FieldType;
use Setono\SyliusCMSPlugin\Binding\BindingType;
use Setono\SyliusCMSPlugin\Field\Field;

/**
 * A project's own field type, with its own field control, which a number binding can fill
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
#[FieldType(code: 'app_rating', translatable: false, bindable: [BindingType::Number], control: 'app-rating')]
final class Rating extends Field
{
}
