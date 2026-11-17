<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection\Compiler\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsConditionKind;
use Setono\SyliusCMSPlugin\Attribute\AsSource;
use Setono\SyliusCMSPlugin\Attribute\FieldType;
use Setono\SyliusCMSPlugin\Field\Field;
use Setono\SyliusCMSPlugin\Field\Text;

/**
 * The shipped Text field type's code, declared again
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
#[FieldType(code: 'text', translatable: true, control: 'app-text')]
final class TextAgain extends Field
{
}
