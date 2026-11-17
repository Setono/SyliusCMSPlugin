<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

/**
 * A placement as the shop reads it: plain values, so a decorator of PlacementProviderInterface can cache it.
 * See "Rendering services" in docs/spec/extending.md
 */
final readonly class PlacementData
{
    /**
     * @param string $uuid the placement's UUID
     * @param string $snippetUuid the UUID of the snippet it shows
     * @param string $slot the identifier of the slot it shows the snippet in
     * @param list<array<string, mixed>> $pageScope the page scope's conditions, in the stored condition format
     * @param int $position its place in the order of the slot's placements: lower comes first
     */
    public function __construct(
        public string $uuid,
        public string $snippetUuid,
        public string $slot,
        public array $pageScope,
        public int $position,
    ) {
    }
}
