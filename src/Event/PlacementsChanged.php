<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Event;

/**
 * Dispatched after the transaction that added, removed or reordered placements commits. Placements take effect on save,
 * outside the snippets' drafts (ADR 0005). See "Placements" in docs/spec/slots.md
 */
final readonly class PlacementsChanged implements StorefrontChangedInterface
{
    /**
     * @param list<string> $snippetUuids the snippets whose placements changed
     * @param list<string> $slots the identifiers of the slots they changed in
     */
    public function __construct(
        public array $snippetUuids,
        public array $slots,
    ) {
    }

    public function getAffectedUuids(): array
    {
        return $this->snippetUuids;
    }
}
