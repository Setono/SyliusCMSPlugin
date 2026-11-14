<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture;

use Setono\SyliusCMSPlugin\Attribute\Allow;
use Setono\SyliusCMSPlugin\Attribute\BlockType;

/**
 * Allows any block type, including Slide, which may only sit inside a Gallery
 */
#[BlockType(code: 'gallery', label: 'app.gallery', template: 'gallery.html.twig', children: [new Allow('*')])]
final class Gallery
{
}
