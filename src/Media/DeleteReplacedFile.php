<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

/**
 * The delayed Messenger message that deletes a media item's old file and its variants once the grace period after a
 * replace is over (media.grace_period). It's sent on Sylius's event bus after the replace's transaction commits, and
 * routed to Sylius's main transport, so the shop needs a running worker. See "Replacing a file" in docs/spec/media.md
 */
final readonly class DeleteReplacedFile
{
    /**
     * @param string $path the old file's path in the plugin's storage
     */
    public function __construct(
        public string $mediaItemUuid,
        public string $path,
    ) {
    }
}
