<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture;

use Setono\SyliusCMSPlugin\Attribute\Allow;
use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Block\Heading;

/**
 * Names Heading, with a max, before it allows any block type
 */
#[BlockType(code: 'panel', label: 'app.panel', template: 'panel.html.twig', children: [new Allow(Heading::class, max: 2), new Allow('*')])]
final class Panel
{
}
