<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Event;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The event dispatcher of the services that dispatch their events once their own transaction commits, such as
 * EntryPublished, EntrySettingsChanged and PlacementsChanged. On its own, such a service's transaction is the outermost
 * one, so it dispatches at once. An import runs those services inside its one transaction, all or nothing: it holds
 * their events while it runs, and releases them once it commits, or forgets them when it rolls back, so listeners never
 * hear of a change that didn't happen. See "What an import does" in docs/spec/transfer.md
 *
 * A service that also dispatches a veto, such as BeforeEntryPublish, which must reach listeners at once, keeps Symfony's
 * event dispatcher
 */
final class AfterCommitDispatcher implements EventDispatcherInterface, ResetInterface
{
    /** @var list<array{object, string|null}>|null the events held back, in order, or null while none are held */
    private ?array $held = null;

    public function __construct(private readonly EventDispatcherInterface $eventDispatcher)
    {
    }

    public function dispatch(object $event, ?string $eventName = null): object
    {
        if (null === $this->held) {
            return $this->eventDispatcher->dispatch($event, $eventName);
        }

        $this->held[] = [$event, $eventName];

        return $event;
    }

    /**
     * Holds back every event dispatched from now on, until release() or forget()
     *
     * @throws \LogicException when events are held already: an import doesn't run inside another
     */
    public function hold(): void
    {
        if (null !== $this->held) {
            throw new \LogicException('Events are held already');
        }

        $this->held = [];
    }

    /**
     * Dispatches the events held back, in the order they were dispatched, and stops holding them
     */
    public function release(): void
    {
        $held = $this->held ?? [];
        $this->held = null;

        foreach ($held as [$event, $eventName]) {
            $this->eventDispatcher->dispatch($event, $eventName);
        }
    }

    /**
     * Forgets the events held back, and stops holding them
     */
    public function forget(): void
    {
        $this->held = null;
    }

    public function reset(): void
    {
        $this->forget();
    }
}
