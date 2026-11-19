<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

/**
 * The delayed Messenger message that abandons an import started in the admin that no one confirmed in time, deleting
 * its zip from the media storage (ImportQueueInterface::ABANDONED_AFTER). It's sent on Sylius's event bus and routed to
 * Sylius's main transport, and does nothing for an import that was confirmed or is done. See "Who runs it" in
 * docs/spec/transfer.md
 */
final readonly class AbandonImport
{
    public function __construct(public string $importUuid)
    {
    }
}
