<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Block;

use Setono\SyliusCMSPlugin\Attribute\Allow;
use Setono\SyliusCMSPlugin\Attribute\BlockType;

/**
 * One column of a Columns block, holding any blocks. See "Columns and Column" in docs/spec/blocks.md
 */
#[BlockType(
    code: 'column',
    label: 'setono_sylius_cms.block_type.column',
    template: '@SetonoSyliusCMSPlugin/block/column.html.twig',
    version: 1,
    children: [new Allow('*')],
    parents: [Columns::class],
    thumbnail: '/bundles/setonosyliuscmsplugin/thumbnail/block/column.svg',
)]
final class Column
{
}
