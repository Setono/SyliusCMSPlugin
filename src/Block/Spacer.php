<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Block;

use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Field\Choice;

/**
 * Empty vertical space. See "Spacer" in docs/spec/blocks.md
 */
#[BlockType(
    code: 'spacer',
    label: 'setono_sylius_cms.block_type.spacer',
    template: '@SetonoSyliusCMSPlugin/block/spacer.html.twig',
    version: 1,
    thumbnail: '/bundles/setonosyliuscmsplugin/thumbnail/block/spacer.svg',
)]
final class Spacer
{
    #[Choice(
        label: 'setono_sylius_cms.field.spacer.size',
        choices: [
            'small' => 'setono_sylius_cms.choice.spacer_size.small',
            'medium' => 'setono_sylius_cms.choice.spacer_size.medium',
            'large' => 'setono_sylius_cms.choice.spacer_size.large',
        ],
    )]
    public string $size = 'medium';
}
