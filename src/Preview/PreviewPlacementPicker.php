<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preview;

use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Registry\SlotMetadata;
use Setono\SyliusCMSPlugin\Rendering\PlacementPickerInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * In a snippet's preview and on its share links, lets the placement they show win in its slot with the snippet's draft,
 * as if it won for shoppers, whatever its page scope, its cap and the snippet's own visibility. Every other slot, and
 * the slot's other placements, pick as for shoppers. See "Preview mode and share links" in docs/spec/rendering.md
 */
final readonly class PreviewPlacementPicker implements PlacementPickerInterface
{
    public function __construct(
        private PlacementPickerInterface $picker,
        private PreviewContext $previewContext,
        private PreviewedEntryProvider $previewedEntry,
    ) {
    }

    public function pick(SlotMetadata $slot, array $placements, RenderContext $context): array
    {
        $candidates = $this->picker->pick($slot, $placements, $context);

        $placementUuid = $this->previewContext->getPreview()?->placementUuid;
        $previewed = null === $placementUuid ? null : PreviewedSlot::previewedIn($candidates, $placementUuid);
        if (null === $previewed) {
            return $candidates;
        }

        $draft = $this->previewedEntry->getPreviewedContent($context->localeCode);

        return $draft instanceof SnippetInterface ? PreviewedSlot::asIfItWon($slot, $candidates, $previewed, $draft) : $candidates;
    }
}
