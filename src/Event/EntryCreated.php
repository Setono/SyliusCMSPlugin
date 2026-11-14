<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Event;

/**
 * Dispatched after the transaction that created an entry commits. A new entry isn't published, so shoppers see no change
 */
final readonly class EntryCreated
{
    public function __construct(
        public string $uuid,
        public string $contentType,
    ) {
    }
}
