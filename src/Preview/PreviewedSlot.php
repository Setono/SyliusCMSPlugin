<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preview;

use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Registry\SlotMetadata;
use Setono\SyliusCMSPlugin\Rendering\CandidatePlacement;
use Setono\SyliusCMSPlugin\Rendering\PlacementOutcome;

/**
 * The slot of the placement a snippet's preview or share link shows. The placement wins there with the snippet's draft,
 * whatever its scope and the snippet's own visibility, and takes one seat of a capped slot, so the other matching
 * placements win in editor order while seats are left. The builder's top bar says which snippets shoppers see instead
 * where the placement loses its capped slot. See "Snippet preview" in docs/spec/builder.md and "Preview mode and share
 * links" in docs/spec/rendering.md
 *
 * @internal
 */
final class PreviewedSlot
{
    private function __construct()
    {
    }

    /**
     * The candidate of the placement with the UUID, or null when the slot doesn't hold it
     *
     * @param list<CandidatePlacement> $candidates every placement in the slot
     */
    public static function previewedIn(array $candidates, string $placementUuid): ?CandidatePlacement
    {
        foreach ($candidates as $candidate) {
            if ($candidate->placement->uuid === $placementUuid) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * The candidates as the preview shows the slot: the previewed placement wins with the draft, and the others that
     * match keep the seats of the cap that are left
     *
     * @param list<CandidatePlacement> $candidates every placement in the slot, in editor order, as shoppers get them
     * @param CandidatePlacement $previewed the previewed placement's, one of the candidates (previewedIn())
     *
     * @return list<CandidatePlacement>
     */
    public static function asIfItWon(SlotMetadata $slot, array $candidates, CandidatePlacement $previewed, SnippetInterface $draft): array
    {
        // The previewed placement takes one seat wherever it is
        $won = 1;
        $shown = [];
        foreach ($candidates as $candidate) {
            if ($candidate === $previewed) {
                $shown[] = new CandidatePlacement($candidate->placement, PlacementOutcome::Wins, $draft, true);

                continue;
            }

            if (!self::matches($candidate) || null === $candidate->snippet) {
                $shown[] = $candidate;

                continue;
            }

            $wins = $slot->hasSeatFor($won);
            if ($wins) {
                ++$won;
            }

            $shown[] = new CandidatePlacement($candidate->placement, $wins ? PlacementOutcome::Wins : PlacementOutcome::CapReached, $candidate->snippet);
        }

        return $shown;
    }

    /**
     * The snippets shoppers see in the slot where the placement with the UUID loses it to the cap, as if its snippet
     * were visible to them, since the preview shows a draft whatever its own visibility. Null where it would win, where
     * its scope doesn't match the page, or when it isn't in the slot
     *
     * @param list<CandidatePlacement> $candidates every placement in the slot, in editor order, as shoppers get them
     *
     * @return list<SnippetInterface>|null
     */
    public static function shownInstead(SlotMetadata $slot, array $candidates, string $placementUuid): ?array
    {
        $previewed = self::previewedIn($candidates, $placementUuid);
        if (null === $previewed || null === $slot->cap || !in_array($previewed->outcome, [PlacementOutcome::CapReached, PlacementOutcome::SnippetNotVisible], true)) {
            return null;
        }

        $matchingAbove = 0;
        foreach ($candidates as $candidate) {
            if ($candidate === $previewed) {
                break;
            }

            if (self::matches($candidate)) {
                ++$matchingAbove;
            }
        }

        if ($slot->hasSeatFor($matchingAbove)) {
            return null;
        }

        $winners = [];
        foreach ($candidates as $candidate) {
            if ($candidate->wins()) {
                $winners[] = $candidate->snippet;
            }
        }

        return $winners;
    }

    /**
     * Whether it would win but for the cap: its scope matches and its snippet is visible and fits the slot
     */
    private static function matches(CandidatePlacement $candidate): bool
    {
        return PlacementOutcome::Wins === $candidate->outcome || PlacementOutcome::CapReached === $candidate->outcome;
    }
}
