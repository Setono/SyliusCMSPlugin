<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Block;

use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Field\RichText as RichTextField;
use Setono\SyliusCMSPlugin\Value\RichText as RichTextValue;

/**
 * Formatted text. See "Rich text" in docs/spec/blocks.md
 */
#[BlockType(
    code: 'rich_text',
    label: 'setono_sylius_cms.block_type.rich_text',
    template: '@SetonoSyliusCMSPlugin/block/rich_text.html.twig',
    version: 1,
    thumbnail: '/bundles/setonosyliuscmsplugin/thumbnail/block/rich_text.svg',
)]
final class RichText
{
    #[RichTextField(label: 'setono_sylius_cms.field.rich_text.content')]
    public ?RichTextValue $content = null;
}
