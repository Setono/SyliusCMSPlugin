<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Tree\Fixture;

use Setono\SyliusCMSPlugin\Attribute\Allow;
use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Field\Media;
use Setono\SyliusCMSPlugin\Field\MediaList;
use Setono\SyliusCMSPlugin\Field\Text;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Value\ItemList;

#[BlockType(code: 'gallery', label: 'Gallery', template: 'gallery.html.twig', children: [new Allow('*')])]
final class Gallery
{
    #[Text(label: 'Heading')]
    public ?string $heading = null;

    #[Media(label: 'Cover')]
    public ?MediaItemInterface $cover = null;

    #[Media(label: 'Poster', translatable: true)]
    public ?MediaItemInterface $poster = null;

    /** @var ItemList<MediaItemInterface>|null */
    #[MediaList(label: 'Images')]
    public ?ItemList $images = null;
}
