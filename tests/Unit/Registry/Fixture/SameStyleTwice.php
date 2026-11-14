<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture;

use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Attribute\Style;

/**
 * A block type that declares two block styles with the same code
 */
#[BlockType(
    code: 'same_style_twice',
    label: 'app.same_style_twice',
    template: 'same_style_twice.html.twig',
    styles: [new Style('dark', 'app.dark', 'app-dark'), new Style('dark', 'app.darker', 'app-darker')],
)]
final class SameStyleTwice
{
}
