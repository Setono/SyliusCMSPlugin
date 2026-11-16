<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture;

use Setono\SyliusCMSPlugin\Attribute\Allow;
use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Block\Column;
use Setono\SyliusCMSPlugin\Block\Heading;

/**
 * Allows any block type, exactly one Heading, and names Column, which may only sit inside Columns
 */
#[BlockType(
    code: 'card',
    label: 'app.card',
    template: 'card.html.twig',
    children: [new Allow('*'), new Allow(Heading::class, min: 1, max: 1), new Allow(Column::class)],
)]
final class Card
{
}
