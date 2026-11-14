<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Block;

use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Field\RichText as RichTextField;
use Setono\SyliusCMSPlugin\Field\Text;
use Setono\SyliusCMSPlugin\Value\RichText as RichTextValue;

/**
 * One item of an Accordion: its title, and the content it shows when it's open.
 * See "Accordion and Accordion item" in docs/spec/blocks.md
 */
#[BlockType(
    code: 'accordion_item',
    label: 'setono_sylius_cms.block_type.accordion_item',
    template: '@SetonoSyliusCMSPlugin/block/accordion_item.html.twig',
    version: 1,
    parents: [Accordion::class],
    thumbnail: '/bundles/setonosyliuscmsplugin/thumbnail/block/accordion_item.svg',
)]
final class AccordionItem
{
    #[Text(label: 'setono_sylius_cms.field.accordion_item.title', required: true)]
    public ?string $title = null;

    #[RichTextField(label: 'setono_sylius_cms.field.accordion_item.content')]
    public ?RichTextValue $content = null;
}
