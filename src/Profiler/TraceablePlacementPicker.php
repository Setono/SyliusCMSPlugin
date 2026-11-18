<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Profiler;

use Setono\SyliusCMSPlugin\Registry\SlotMetadata;
use Setono\SyliusCMSPlugin\Rendering\PlacementPickerInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * Records every candidate placement of the slot rendering now, with its outcome, and the render context it was picked
 * in. Registered in debug only
 *
 * @internal
 */
final readonly class TraceablePlacementPicker implements PlacementPickerInterface
{
    public function __construct(
        private PlacementPickerInterface $picker,
        private RenderedSlots $slots,
    ) {
    }

    public function pick(SlotMetadata $slot, array $placements, RenderContext $context): array
    {
        $candidates = $this->picker->pick($slot, $placements, $context);

        $trace = $this->slots->current();
        if (null !== $trace && $trace->identifier === $slot->identifier) {
            $trace->candidates = $candidates;
            $trace->renderContext = $context;
        }

        return $candidates;
    }
}
