<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Event;

/**
 * Dispatched after the transaction that cancelled an entry's scheduled publish commits, which unlocked its draft
 */
final readonly class EntryPublishCancelled
{
    public function __construct(public string $uuid)
    {
    }
}
