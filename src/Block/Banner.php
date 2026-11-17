<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Block;

use Setono\SyliusCMSPlugin\Attribute\Allow;
use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Field\Media;
use Setono\SyliusCMSPlugin\Field\RichText as RichTextField;
use Setono\SyliusCMSPlugin\Field\Text;
use Setono\SyliusCMSPlugin\Field\Toggle;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Value\RichText as RichTextValue;

/**
 * An image with an optional mobile image for narrow screens, and a heading, text and buttons over it. Its buttons are
 * Button children. Full width, it breaks out of the shop's container directly inside the tree. See "Banner" in
 * docs/spec/blocks.md
 */
#[BlockType(
    code: 'banner',
    label: 'setono_sylius_cms.block_type.banner',
    template: '@SetonoSyliusCMSPlugin/block/banner.html.twig',
    version: 1,
    children: [new Allow(Button::class)],
    thumbnail: '/bundles/setonosyliuscmsplugin/thumbnail/block/banner.svg',
)]
final class Banner
{
    #[Media(label: 'setono_sylius_cms.field.banner.image')]
    public ?MediaItemInterface $image = null;

    /** Shown instead of the image below the shop's mobile breakpoint */
    #[Media(label: 'setono_sylius_cms.field.banner.mobile_image', help: 'setono_sylius_cms.field.banner.mobile_image_help')]
    public ?MediaItemInterface $mobileImage = null;

    /** Overrides the image's alt text for this use. Empty, it falls back to the media item's */
    #[Text(label: 'setono_sylius_cms.field.banner.alt', help: 'setono_sylius_cms.field.banner.alt_help', translatable: true)]
    public ?string $alt = null;

    #[Text(label: 'setono_sylius_cms.field.banner.heading')]
    public ?string $heading = null;

    #[RichTextField(label: 'setono_sylius_cms.field.banner.text')]
    public ?RichTextValue $text = null;

    #[Toggle(label: 'setono_sylius_cms.field.banner.full_width')]
    public bool $fullWidth = false;
}
