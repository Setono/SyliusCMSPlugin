<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Event;

/**
 * Dispatched after the transaction that deleted a media item commits, once its file and variants are deleted. See
 * "Deleting" in docs/spec/media.md
 */
final readonly class MediaItemDeleted implements StorefrontChangedInterface
{
    public function __construct(public string $uuid)
    {
    }

    public function getAffectedUuids(): array
    {
        return [$this->uuid];
    }
}
