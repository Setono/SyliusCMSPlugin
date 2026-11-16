<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

/**
 * The delayed Messenger message that runs a scheduled publish when its time comes. It's sent on Sylius's event bus and
 * routed to Sylius's main transport, the way Sylius 1.14 schedules catalog promotions, so the shop needs a running
 * worker. It carries the scheduled publish's token, so the message of one that was cancelled or replaced does nothing.
 * See "Scheduled publishes" in docs/spec/publishing.md
 */
final readonly class ScheduledPublishDue
{
    public function __construct(
        public string $entryUuid,
        public string $token,
    ) {
    }
}
