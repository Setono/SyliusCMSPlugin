<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Event;

/**
 * Dispatched after the transaction that scheduled a publish of an entry commits. Shoppers see no change until it runs
 */
final readonly class EntryPublishScheduled
{
    /**
     * @param \DateTimeImmutable $at when it publishes, in UTC
     */
    public function __construct(
        public string $uuid,
        public \DateTimeImmutable $at,
    ) {
    }
}
