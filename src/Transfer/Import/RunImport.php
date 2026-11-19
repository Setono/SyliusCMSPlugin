<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

/**
 * The Messenger message that has the worker run an import started in the admin: its dry run once the zip is uploaded,
 * or the import once an admin user confirms it. It's sent on Sylius's event bus and routed to Sylius's main transport,
 * as scheduled publishes are, so the shop needs a running worker. It carries the import's UUID, and does nothing for an
 * import that doesn't wait for the worker. See "Who runs it" in docs/spec/transfer.md
 */
final readonly class RunImport
{
    public function __construct(public string $importUuid)
    {
    }
}
