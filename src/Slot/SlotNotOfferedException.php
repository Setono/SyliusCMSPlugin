<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Slot;

/**
 * A snippet can't be placed in the slot: the code doesn't declare it, or it doesn't provide the context the snippet needs
 */
final class SlotNotOfferedException extends \InvalidArgumentException
{
    public static function undeclared(string $slot): self
    {
        return new self(sprintf('There is no slot "%s"', $slot));
    }

    public static function withoutContext(string $slot): self
    {
        return new self(sprintf('The slot "%s" doesn\'t provide the context the snippet needs', $slot));
    }
}
