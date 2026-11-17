<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Block;

use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Field\Link;
use Setono\SyliusCMSPlugin\Field\Media;
use Setono\SyliusCMSPlugin\Field\Text;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Value\Link as LinkValue;

/**
 * An image from the media library, rendered responsively by cms_image(), with an alt text for this use, a caption and
 * a link. Captions are block fields, because they depend on where the image is used. See "Image" in docs/spec/blocks.md
 */
#[BlockType(
    code: 'image',
    label: 'setono_sylius_cms.block_type.image',
    template: '@SetonoSyliusCMSPlugin/block/image.html.twig',
    version: 1,
    thumbnail: '/bundles/setonosyliuscmsplugin/thumbnail/block/image.svg',
)]
final class Image
{
    #[Media(label: 'setono_sylius_cms.field.image.image', required: true)]
    public ?MediaItemInterface $image = null;

    /** Overrides the media item's alt text for this use. Empty, it falls back to the media item's */
    #[Text(label: 'setono_sylius_cms.field.image.alt', help: 'setono_sylius_cms.field.image.alt_help', translatable: true)]
    public ?string $alt = null;

    #[Text(label: 'setono_sylius_cms.field.image.caption', translatable: true)]
    public ?string $caption = null;

    #[Link(label: 'setono_sylius_cms.field.image.link')]
    public ?LinkValue $link = null;
}
