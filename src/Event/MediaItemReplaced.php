<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Event;

/**
 * Dispatched after the transaction that replaced a media item's file commits. The new file takes effect at once
 * wherever the media item is used (ADR 0013). See "Replacing a file" in docs/spec/media.md
 */
final readonly class MediaItemReplaced implements StorefrontChangedInterface
{
    public function __construct(public string $uuid)
    {
    }

    public function getAffectedUuids(): array
    {
        return [$this->uuid];
    }
}
