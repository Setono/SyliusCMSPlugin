<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Block;

use Setono\SyliusCMSPlugin\Attribute\Allow;
use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Field\Choice;
use Setono\SyliusCMSPlugin\Field\Number;
use Setono\SyliusCMSPlugin\Field\Toggle;

/**
 * A carousel whose slides are its visible children, of any block type. The track scrolls with CSS scroll-snap, and the
 * shipped script (shop/cms.js) adds buttons, dots and autoplay. See "Carousel" in docs/spec/blocks.md
 */
#[BlockType(
    code: 'carousel',
    label: 'setono_sylius_cms.block_type.carousel',
    template: '@SetonoSyliusCMSPlugin/block/carousel.html.twig',
    version: 1,
    children: [new Allow('*')],
    thumbnail: '/bundles/setonosyliuscmsplugin/thumbnail/block/carousel.svg',
)]
final class Carousel
{
    /** Slides shown at once on wide screens. The stylesheet shows at most 2 on narrow screens, and 1 on the narrowest */
    #[Choice(
        label: 'setono_sylius_cms.field.carousel.per_view',
        choices: [
            '1' => 'setono_sylius_cms.choice.carousel_per_view.1',
            '2' => 'setono_sylius_cms.choice.carousel_per_view.2',
            '3' => 'setono_sylius_cms.choice.carousel_per_view.3',
            '4' => 'setono_sylius_cms.choice.carousel_per_view.4',
        ],
    )]
    public string $perView = '1';

    #[Toggle(label: 'setono_sylius_cms.field.carousel.autoplay')]
    public bool $autoplay = false;

    /** The seconds each slide shows while it autoplays */
    #[Number(label: 'setono_sylius_cms.field.carousel.interval', help: 'setono_sylius_cms.field.carousel.interval_help', min: 2, max: 30, integer: true)]
    public int $interval = 5;
}
