<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Block;

use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Attribute\Style;
use Setono\SyliusCMSPlugin\Field\Link;
use Setono\SyliusCMSPlugin\Value\Link as LinkValue;

/**
 * A link shown as a button, labelled with the link's label or else the current name of what it leads to. A link that
 * doesn't resolve renders no button. See "Button" in docs/spec/blocks.md
 */
#[BlockType(
    code: 'button',
    label: 'setono_sylius_cms.block_type.button',
    template: '@SetonoSyliusCMSPlugin/block/button.html.twig',
    version: 1,
    styles: [
        new Style('primary', 'setono_sylius_cms.block_style.primary', 'cms-button--primary'),
        new Style('secondary', 'setono_sylius_cms.block_style.secondary', 'cms-button--secondary'),
    ],
    thumbnail: '/bundles/setonosyliuscmsplugin/thumbnail/block/button.svg',
)]
final class Button
{
    #[Link(label: 'setono_sylius_cms.field.button.link', required: true)]
    public ?LinkValue $link = null;
}
