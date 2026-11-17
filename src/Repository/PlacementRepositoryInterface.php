<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\PlacementInterface;

/**
 * The placements of snippets in slots. Change them through PlacementEditorInterface. The shop reads them through
 * Rendering\PlacementProviderInterface. See "Placements" in docs/spec/slots.md
 */
interface PlacementRepositoryInterface
{
    public function findOneByUuid(string $uuid): ?PlacementInterface;

    /**
     * The snippet's placements, by slot and then in each slot's order. The snippet is found by its UUID, so its working
     * copy finds them too
     *
     * @return list<PlacementInterface>
     */
    public function findBySnippet(EntryInterface $snippet): array;

    /**
     * Every placement in these slots, from every snippet, with its snippet, by slot and then in each slot's order
     *
     * @param list<string> $slots slot identifiers
     *
     * @return list<PlacementInterface>
     */
    public function findBySlots(array $slots): array;

    /**
     * The position after the last of the slot's placements, where a new placement goes: 0 in a slot with none
     */
    public function nextPosition(string $slot): int;
}
