<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

use Setono\SyliusCMSPlugin\Registry\SlotMetadata;

/**
 * Picks the placements a slot shows, before anything renders: those that match, in editor order, and in a capped slot
 * only the first `cap` of them. A placement matches when its page scope matches the page and its snippet is visible to
 * the shopper. Its blocks don't count, so a snippet whose blocks are all hidden still wins, and shows nothing. It
 * answers every placement with its outcome, not only the winners, so a preview can say which snippet wins in place of
 * the previewed one, and a profiler why a placement didn't show. See "Rendering a slot" in docs/spec/rendering.md and
 * "Capped slots" in docs/spec/targeting.md
 */
interface PlacementPickerInterface
{
    /**
     * @param list<PlacementData> $placements the slot's placements, in editor order
     * @param RenderContext $context with the host-page context the slot provides
     *
     * @return list<CandidatePlacement> each placement given, in the order given, with its outcome
     */
    public function pick(SlotMetadata $slot, array $placements, RenderContext $context): array;
}
