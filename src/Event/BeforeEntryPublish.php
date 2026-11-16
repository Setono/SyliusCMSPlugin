<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Event;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched before a publish, when scheduling one, and again when a scheduled publish runs.
 * A listener stops the publish by calling veto() with a message the builder or admin shows
 */
final class BeforeEntryPublish extends Event
{
    private ?string $vetoMessage = null;

    /**
     * @param EntryInterface $entry the entry as it will be published: its working copy, which is the draft, or the rows of
     *                              an entry that has none. It's the copy that is then published, so listeners read it and leave it as it is
     */
    public function __construct(
        public readonly EntryInterface $entry,
        public readonly bool $scheduled,
    ) {
    }

    /**
     * Stops the publish, and the listeners after this one
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
