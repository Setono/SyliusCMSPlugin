<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Event;

use Setono\SyliusCMSPlugin\Transfer\Import\ImportResult;

/**
 * Dispatched after an import's transaction commits, with its result: the UUIDs of the records it created, updated and
 * left unchanged, and the references that don't resolve. The events of what it wrote, such as EntrySettingsChanged and
 * EntryPublished, went out just before. A dry run or a refused import changes nothing, and dispatches none. See "What
 * an import does" in docs/spec/transfer.md and "Events" in docs/spec/extending.md
 */
final readonly class ImportFinished implements StorefrontChangedInterface
{
    public function __construct(public ImportResult $result)
    {
    }

    /**
     * The records it created or updated. An import that changed nothing touched none
     */
    public function getAffectedUuids(): array
    {
        return [...$this->result->created(), ...$this->result->updated()];
    }
}
