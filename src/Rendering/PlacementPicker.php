<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

use Setono\SyliusCMSPlugin\Registry\SlotMetadata;

/**
 * Checks the placements in the order of "Rendering a slot" in docs/spec/rendering.md: their page scopes, then for those
 * that match, with one query, whether their snippets are visible, and whether a snippet's published content needs more
 * context than the slot provides, then the slot's cap
 */
final readonly class PlacementPicker implements PlacementPickerInterface
{
    public function __construct(
        private PageScopeMatcherInterface $pageScopes,
        private VisibleSnippetsInterface $visibleSnippets,
    ) {
    }

    public function pick(SlotMetadata $slot, array $placements, RenderContext $context): array
    {
        $inScope = [];
        foreach ($this->pageScopes->matching($placements, $context) as $placement) {
            $inScope[$placement->uuid] = $placement->snippetUuid;
        }

        $snippets = [] === $inScope ? [] : $this->visibleSnippets->get(array_values(array_unique($inScope)), $context);

        $candidates = [];
        $wins = 0;
        foreach ($placements as $placement) {
            $snippet = isset($inScope[$placement->uuid]) ? ($snippets[$placement->snippetUuid] ?? null) : null;

            $outcome = match (true) {
                !isset($inScope[$placement->uuid]) => PlacementOutcome::ScopeDoesNotMatch,
                null === $snippet => PlacementOutcome::SnippetNotVisible,
                !$slot->providesAll($snippet->getContext()) => PlacementOutcome::ContextNotProvided,
                !$slot->hasSeatFor($wins) => PlacementOutcome::CapReached,
                default => PlacementOutcome::Wins,
            };

            if (PlacementOutcome::Wins === $outcome) {
                ++$wins;
            }

            $candidates[] = new CandidatePlacement($placement, $outcome, $snippet);
        }

        return $candidates;
    }
}
