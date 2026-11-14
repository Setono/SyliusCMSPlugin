<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture;

use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Attribute\Style;

/**
 * A block type with a block style whose code doesn't match ^[a-z][a-z0-9_]*$
 */
#[BlockType(code: 'invalid_style', label: 'app.invalid_style', template: 'invalid_style.html.twig', styles: [new Style('Dark-Blue', 'app.dark_blue', 'app-dark-blue')])]
final class InvalidStyleCode
{
}
