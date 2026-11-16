<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Field\Fixture;

use Setono\SyliusCMSPlugin\Field\Media;
use Setono\SyliusCMSPlugin\Field\MediaList;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Value\ItemList;

final class WithMedia
{
    #[Media(label: 'app.image')]
    public ?MediaItemInterface $image = null;

    /** @var ItemList<MediaItemInterface>|null */
    #[MediaList(label: 'app.documents', kinds: ['document'], max: 3)]
    public ?ItemList $documents = null;
}
