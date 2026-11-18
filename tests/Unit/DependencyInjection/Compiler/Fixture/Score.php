<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection\Compiler\Fixture;

use Setono\SyliusCMSPlugin\Attribute\FieldType;
use Setono\SyliusCMSPlugin\Field\Field;

/**
 * One field type that several field attributes share, as the shipped list fields share ListField
 */
#[FieldType(code: 'app_score', control: 'app-score')]
abstract class Score extends Field
{
}
