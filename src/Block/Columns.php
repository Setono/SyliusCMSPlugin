<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Block;

use Setono\SyliusCMSPlugin\Attribute\Allow;
use Setono\SyliusCMSPlugin\Attribute\BlockType;

/**
 * Lays its Column children side by side at equal widths, and wraps them below --cms-column-min-width.
 * See "Columns and Column" in docs/spec/blocks.md
 */
#[BlockType(
    code: 'columns',
    label: 'setono_sylius_cms.block_type.columns',
    template: '@SetonoSyliusCMSPlugin/block/columns.html.twig',
    version: 1,
    children: [new Allow(Column::class, min: 2, max: 4)],
    thumbnail: '/bundles/setonosyliuscmsplugin/thumbnail/block/columns.svg',
)]
final class Columns
{
}
