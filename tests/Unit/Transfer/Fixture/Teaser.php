<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Transfer\Fixture;

use Setono\SyliusCMSPlugin\Attribute\Allow;
use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Field\Entry;
use Setono\SyliusCMSPlugin\Field\Media;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;

/**
 * A block type with a media item, which an export brings along, and a link to an entry, which it doesn't
 */
#[BlockType(code: 'teaser', label: 'Teaser', template: 'teaser.html.twig', children: [new Allow('*')])]
final class Teaser
{
    #[Media(label: 'Image')]
    public ?MediaItemInterface $image = null;

    #[Entry(label: 'Read more')]
    public ?EntryInterface $readMore = null;
}
