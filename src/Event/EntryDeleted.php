<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Event;

/**
 * Dispatched after the transaction that deleted an entry commits
 */
final readonly class EntryDeleted implements StorefrontChangedInterface
{
    public function __construct(
        public string $uuid,
        public string $contentType,
    ) {
    }

    public function getAffectedUuids(): array
    {
        return [$this->uuid];
    }
}
