<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Block;

use Setono\SyliusCMSPlugin\Attribute\Allow;
use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Attribute\Style;
use Setono\SyliusCMSPlugin\Field\Toggle;

/**
 * A wrapper for other blocks, with a background from its block style. See "Group" in docs/spec/blocks.md
 */
#[BlockType(
    code: 'group',
    label: 'setono_sylius_cms.block_type.group',
    template: '@SetonoSyliusCMSPlugin/block/group.html.twig',
    version: 1,
    children: [new Allow('*')],
    styles: [
        new Style('muted', 'setono_sylius_cms.block_style.muted', 'cms-group--muted'),
        new Style('dark', 'setono_sylius_cms.block_style.dark', 'cms-group--dark'),
    ],
    thumbnail: '/bundles/setonosyliuscmsplugin/thumbnail/block/group.svg',
)]
final class Group
{
    #[Toggle(label: 'setono_sylius_cms.field.group.full_width')]
    public bool $fullWidth = false;
}
