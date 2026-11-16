<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Block;

use Setono\SyliusCMSPlugin\Attribute\Allow;
use Setono\SyliusCMSPlugin\Attribute\BlockType;

/**
 * Shows only its first child whose visibility conditions match, in tree order, like a capped slot with a cap of 1
 * inside a block tree, such as a campaign banner while its date window is open and a default banner otherwise. It has
 * no fields and no markup of its own: the read pipeline hands its template the one child that shows.
 * See "First match" in docs/spec/blocks.md
 */
#[BlockType(
    code: 'first_match',
    label: 'setono_sylius_cms.block_type.first_match',
    template: '@SetonoSyliusCMSPlugin/block/first_match.html.twig',
    version: 1,
    children: [new Allow('*')],
    thumbnail: '/bundles/setonosyliuscmsplugin/thumbnail/block/first_match.svg',
)]
final class FirstMatch
{
}
