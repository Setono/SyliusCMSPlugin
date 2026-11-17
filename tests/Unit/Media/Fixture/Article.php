<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Media\Fixture;

use Setono\SyliusCMSPlugin\Attribute\Allow;
use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Field\Media;
use Setono\SyliusCMSPlugin\Field\MediaList;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Value\ItemList;

/**
 * A block type with media items in a Media field, a Media list field, and a field that links to them among other
 * things, as rich text does
 */
#[BlockType(code: 'article', label: 'Article', template: 'article.html.twig', children: [new Allow('*')])]
final class Article
{
    #[Media(label: 'Cover')]
    public ?MediaItemInterface $cover = null;

    /** @var ItemList<MediaItemInterface>|null */
    #[MediaList(label: 'Images')]
    public ?ItemList $images = null;

    /** @var list<array{type: string, id: string}>|null */
    #[Linked(label: 'Body')]
    public ?array $body = null;
}
