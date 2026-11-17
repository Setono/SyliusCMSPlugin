<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection\Compiler\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsConditionKind;
use Setono\SyliusCMSPlugin\Attribute\AsSource;
use Setono\SyliusCMSPlugin\Attribute\FieldType;
use Setono\SyliusCMSPlugin\Field\Field;
use Setono\SyliusCMSPlugin\Field\Text;

/**
 * Field types a project declares, right and wrong, and classes whose fields have them, for CheckFieldTypesPassTest
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
#[FieldType(code: 'app_stars', control: 'app-stars')]
final class Stars extends Field
{
}
