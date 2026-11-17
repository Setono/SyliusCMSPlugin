<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Block;

use Setono\SyliusCMSPlugin\Attribute\Allow;
use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Field\Text;

/**
 * Simple navigation, such as footer links in a snippet, until menus arrive: a heading and a list of Link children. Each
 * link is a block, because a link has fields of its own and there's no repeater. See "Link list and Link" in
 * docs/spec/blocks.md and ADR 0003
 */
#[BlockType(
    code: 'link_list',
    label: 'setono_sylius_cms.block_type.link_list',
    template: '@SetonoSyliusCMSPlugin/block/link_list.html.twig',
    version: 1,
    children: [new Allow(Link::class)],
    thumbnail: '/bundles/setonosyliuscmsplugin/thumbnail/block/link_list.svg',
)]
final class LinkList
{
    #[Text(label: 'setono_sylius_cms.field.link_list.heading')]
    public ?string $heading = null;
}
