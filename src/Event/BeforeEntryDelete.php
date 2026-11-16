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
    use VetoTrait;

    /**
     * @param EntryInterface $entry the entry about to be deleted. Listeners read it and leave it as it is
     */
    public function __construct(public readonly EntryInterface $entry)
    {
    }
}
