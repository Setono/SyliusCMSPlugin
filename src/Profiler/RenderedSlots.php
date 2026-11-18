<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Profiler;

use Symfony\Contracts\Service\ResetInterface;

/**
 * The slots rendered during the main request, its sub-requests' included, which the traceable decorators record and
 * the profiler panel shows, and the blocks that threw outside any slot, such as in an entry's own tree
 *
 * @internal
 */
final class RenderedSlots implements ResetInterface
{
    /** @var list<SlotTrace> in the order they started */
    private array $slots = [];

    /** @var list<SlotTrace> the slots rendering now, the innermost last */
    private array $rendering = [];

    /** @var list<RecordedFailure> */
    private array $failuresOutsideSlots = [];

    public function start(string $identifier, ?string $subRequest = null): SlotTrace
    {
        $slot = new SlotTrace($identifier, $subRequest);
        $this->slots[] = $slot;
        $this->rendering[] = $slot;

        return $slot;
    }

    /**
     * @param float $duration how long it took, in milliseconds
     */
    public function end(SlotTrace $slot, float $duration): void
    {
        $slot->duration = $duration;
        $this->rendering = array_values(array_filter($this->rendering, static fn (SlotTrace $rendering): bool => $rendering !== $slot));
    }

    /**
     * The slot rendering now, the innermost one inside another, or null outside any slot
     */
    public function current(): ?SlotTrace
    {
        return [] === $this->rendering ? null : $this->rendering[array_key_last($this->rendering)];
    }

    /**
     * Records the failure in the slot rendering now, or outside any slot
     */
    public function failed(RecordedFailure $failure): void
    {
        $slot = $this->current();
        if (null === $slot) {
            $this->failuresOutsideSlots[] = $failure;
        } else {
            $slot->failures[] = $failure;
        }
    }

    /**
     * @return list<SlotTrace>
     */
    public function all(): array
    {
        return $this->slots;
    }

    /**
     * @return list<RecordedFailure>
     */
    public function failuresOutsideSlots(): array
    {
        return $this->failuresOutsideSlots;
    }

    /**
     * The next main request starts with none
     */
    public function reset(): void
    {
        $this->slots = $this->rendering = $this->failuresOutsideSlots = [];
    }
}
