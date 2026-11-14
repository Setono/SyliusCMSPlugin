<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection\Compiler;

use Setono\SyliusCMSPlugin\Attribute\BlockType;

#[BlockType(code: 'heading', label: 'app.heading', template: 'heading.html.twig')]
final class SameCodeAsHeading
{
}
