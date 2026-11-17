<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

use Setono\SyliusCMSPlugin\Model\MediaItemInterface;

/**
 * Deletes a media item that nothing uses. See "Deleting" in docs/spec/media.md
 */
interface MediaItemDeleterInterface
{
    /**
     * Refuses while the usage index lists a use. Otherwise BeforeMediaItemDelete is dispatched, which a listener can
     * veto. Then the media item, its file and its variants are deleted, and MediaItemDeleted is dispatched after the
     * transaction commits
     *
     * @throws MediaItemInUseException
     * @throws MediaItemDeleteVetoedException
     */
    public function delete(MediaItemInterface $mediaItem): void;
}
