<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

use Setono\SyliusCMSPlugin\Model\MediaItemInterface;

/**
 * Saves a media item's metadata. Media items have no drafts, so it takes effect at once wherever the media item is used
 * (ADR 0013). See "Changes take effect on save" in docs/spec/media.md
 */
interface MediaItemSaverInterface
{
    /**
     * Saves what differs from the media item's metadata, bumps its changedAt and dispatches MediaItemSaved after the
     * transaction commits. Saving what it already has changes nothing
     *
     * @throws InvalidMediaChangeException
     */
    public function save(MediaItemInterface $mediaItem, MediaItemMetadata $metadata): void;
}
