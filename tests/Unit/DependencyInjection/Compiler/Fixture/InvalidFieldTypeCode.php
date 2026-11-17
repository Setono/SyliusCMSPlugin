<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection\Compiler\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsConditionKind;
use Setono\SyliusCMSPlugin\Attribute\AsSource;
use Setono\SyliusCMSPlugin\Attribute\FieldType;
use Setono\SyliusCMSPlugin\Field\Field;
use Setono\SyliusCMSPlugin\Field\Text;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
#[FieldType(code: 'App-Stars', control: 'app-stars')]
final class InvalidFieldTypeCode extends Field
{
}
