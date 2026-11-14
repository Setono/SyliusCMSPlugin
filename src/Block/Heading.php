<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Block;

use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Field\Choice;
use Setono\SyliusCMSPlugin\Field\Text;

/**
 * See "Heading" in docs/spec/blocks.md
 */
#[BlockType(
    code: 'heading',
    label: 'setono_sylius_cms.block_type.heading',
    template: '@SetonoSyliusCMSPlugin/block/heading.html.twig',
    version: 1,
)]
final class Heading
{
    #[Text(label: 'setono_sylius_cms.field.heading.text', required: true, translatable: true)]
    public ?string $text = null;

    #[Choice(
        label: 'setono_sylius_cms.field.heading.level',
        choices: [
            'h1' => 'setono_sylius_cms.choice.heading_level.h1',
            'h2' => 'setono_sylius_cms.choice.heading_level.h2',
            'h3' => 'setono_sylius_cms.choice.heading_level.h3',
            'h4' => 'setono_sylius_cms.choice.heading_level.h4',
        ],
    )]
    public string $level = 'h2';
}
