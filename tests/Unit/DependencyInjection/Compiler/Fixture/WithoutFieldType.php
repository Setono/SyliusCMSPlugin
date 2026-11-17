<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection\Compiler\Fixture;

use Setono\SyliusCMSPlugin\Field\Field;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class WithoutFieldType extends Field
{
}
