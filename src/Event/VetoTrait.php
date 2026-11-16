<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Event;

/**
 * A listener's veto of what the event comes before, such as a publish or a delete, with a message the admin shows.
 * For an event that extends Symfony's Event
 */
trait VetoTrait
{
    private ?string $vetoMessage = null;

    /**
     * Stops what the event comes before, and the listeners after this one
     */
    public function veto(string $message): void
    {
        $this->vetoMessage = $message;
        $this->stopPropagation();
    }

    public function isVetoed(): bool
    {
        return null !== $this->vetoMessage;
    }

    public function getVetoMessage(): ?string
    {
        return $this->vetoMessage;
    }
}
