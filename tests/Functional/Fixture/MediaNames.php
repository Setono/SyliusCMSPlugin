<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Field\Media;
use Setono\SyliusCMSPlugin\Field\MediaList;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Value\ItemList;

/**
 * A project's block type with a Media field and a Media list field, whose template prints the names of their media
 * items through cms_value(). Only MediaFieldsKernel registers it
 */
#[BlockType(code: 'media_names', label: 'Media names', template: 'cms/block/media_names.html.twig')]
final class MediaNames
{
    #[Media(label: 'Image')]
    public ?MediaItemInterface $image = null;

    /** @var ItemList<MediaItemInterface>|null */
    #[MediaList(label: 'Gallery', kinds: ['image', 'document'], max: 3)]
    public ?ItemList $gallery = null;
}
