<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Event;

/**
 * Dispatched after the transaction that saved a media item's metadata or moved its focal point commits. The change takes
 * effect at once wherever the media item is used (ADR 0013). See "Changes take effect on save" in docs/spec/media.md
 */
final readonly class MediaItemSaved implements StorefrontChangedInterface
{
    public function __construct(public string $uuid)
    {
    }

    public function getAffectedUuids(): array
    {
        return [$this->uuid];
    }
}
