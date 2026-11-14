<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Block;

use Setono\SyliusCMSPlugin\Attribute\Allow;
use Setono\SyliusCMSPlugin\Attribute\BlockType;

/**
 * Items that open and close, as <details>, with no JS. See "Accordion and Accordion item" in docs/spec/blocks.md
 */
#[BlockType(
    code: 'accordion',
    label: 'setono_sylius_cms.block_type.accordion',
    template: '@SetonoSyliusCMSPlugin/block/accordion.html.twig',
    version: 1,
    children: [new Allow(AccordionItem::class, min: 1)],
    thumbnail: '/bundles/setonosyliuscmsplugin/thumbnail/block/accordion.svg',
)]
final class Accordion
{
}
