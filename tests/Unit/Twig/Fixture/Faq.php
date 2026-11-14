<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Twig\Fixture;

use Setono\SyliusCMSPlugin\Attribute\Allow;
use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Block\Heading;

/**
 * A block type whose template prints its children's fields itself, instead of rendering the children
 */
#[BlockType(code: 'faq', label: 'app.faq', template: 'faq.html.twig', children: [new Allow(Heading::class)])]
final class Faq
{
}
