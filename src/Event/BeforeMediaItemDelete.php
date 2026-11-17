<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Event;

use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched before a media item that nothing uses is deleted. A listener stops the delete by calling veto() with a
 * message the admin shows. See "Deleting" in docs/spec/media.md
 */
final class BeforeMediaItemDelete extends Event
{
    use VetoTrait;

    /**
     * @param MediaItemInterface $mediaItem the media item about to be deleted. Listeners read it and leave it as it is
     */
    public function __construct(public readonly MediaItemInterface $mediaItem)
    {
    }
}
