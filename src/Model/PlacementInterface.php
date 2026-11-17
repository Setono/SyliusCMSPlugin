<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Resource\Model\ResourceInterface;

/**
 * The showing of one snippet in one slot, limited by a page scope, at a position in the slot. It isn't part of the
 * snippet: it takes effect on save, outside the draft (ADR 0005), and it stores the slot's identifier, never a template
 * event's name (ADR 0004). Change placements through PlacementEditorInterface, which fires PlacementsChanged.
 * See "Placements" in docs/spec/slots.md
 */
interface PlacementInterface extends ResourceInterface
{
    public function getId(): ?int;

    public function getUuid(): string;

    public function getSnippet(): SnippetInterface;

    /**
     * The identifier of the slot it shows the snippet in, which the code may no longer declare
     */
    public function getSlot(): string;

    /**
     * The conditions on host-page context that decide which shop pages it shows on, in the stored condition format.
     * Empty means every page the slot appears on
     *
     * @return list<array<string, mixed>>
     */
    public function getPageScope(): array;

    /**
     * Its place in the order of the slot's placements, from every snippet: lower comes first
     */
    public function getPosition(): int;

    public function setPosition(int $position): void;
}
