<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection\Compiler\Fixture;

use Setono\SyliusCMSPlugin\Attribute\FieldType;
use Setono\SyliusCMSPlugin\Field\Field;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
#[FieldType(code: 'app_hearts', control: 'Hearts')]
final class InvalidControl extends Field
{
}
