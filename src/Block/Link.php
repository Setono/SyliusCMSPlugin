<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Block;

use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Field\Link as LinkField;
use Setono\SyliusCMSPlugin\Value\Link as LinkValue;

/**
 * One link of a Link list, labelled with the link's label or else the current name of what it leads to. A link that
 * doesn't resolve renders no list item. See "Link list and Link" in docs/spec/blocks.md
 */
#[BlockType(
    code: 'link',
    label: 'setono_sylius_cms.block_type.link',
    template: '@SetonoSyliusCMSPlugin/block/link.html.twig',
    version: 1,
    parents: [LinkList::class],
    thumbnail: '/bundles/setonosyliuscmsplugin/thumbnail/block/link.svg',
)]
final class Link
{
    #[LinkField(label: 'setono_sylius_cms.field.link.link', required: true)]
    public ?LinkValue $link = null;
}
