<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Slot;

/**
 * An order of a slot's placements that doesn't name each of them exactly once, such as one made before another tab
 * added or removed a placement in the slot
 */
final class OutdatedPlacementOrderException extends \InvalidArgumentException
{
    public function __construct(string $slot)
    {
        parent::__construct(sprintf('The order doesn\'t name each placement in the slot "%s" exactly once', $slot));
    }
}
