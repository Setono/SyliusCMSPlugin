<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Event;

/**
 * Dispatched after the transaction of a publish commits
 */
final readonly class EntryPublished implements StorefrontChangedInterface
{
    /**
     * @param array<string, string> $oldSlugs locale => the slug shoppers saw before the publish, empty on the first publish
     * @param array<string, string> $newSlugs locale => the slug shoppers see now
     */
    public function __construct(
        public string $uuid,
        public string $contentType,
        public array $oldSlugs,
        public array $newSlugs,
        public bool $scheduled,
    ) {
    }

    public function getAffectedUuids(): array
    {
        return [$this->uuid];
    }
}
