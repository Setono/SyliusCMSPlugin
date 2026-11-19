<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Format;

/**
 * A snippet's placement in an export: its slot, its page scope and the placements before it in the source slot,
 * nearest first. It has no changedAt of its own: changing one bumps its snippet's. See "entries/<uuid>.json" and
 * "Placements" in docs/spec/transfer.md
 */
final readonly class ExportedPlacement
{
    /**
     * @param string $slot the slot's identifier
     * @param list<array<string, mixed>> $scope the page scope's conditions, in the stored condition format
     * @param list<string> $preceding the UUIDs of the placements before it in the source slot, from every snippet,
     *                                nearest first
     */
    public function __construct(
        public string $uuid,
        public string $slot,
        public array $scope,
        public array $preceding,
    ) {
    }
}
