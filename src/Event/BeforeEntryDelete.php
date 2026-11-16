<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Event;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched before an entry is deleted. A listener stops the delete by calling veto() with a message the admin shows
 */
final class BeforeEntryDelete extends Event
{
    private ?string $vetoMessage = null;

    /**
     * @param EntryInterface $entry the entry about to be deleted. Listeners read it and leave it as it is
     */
    public function __construct(public readonly EntryInterface $entry)
    {
    }

    /**
     * Stops the delete, and the listeners after this one
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
