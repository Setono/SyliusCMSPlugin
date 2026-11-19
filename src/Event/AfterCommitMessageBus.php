<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Event;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The message bus of what must be sent only once a transaction commits, such as the delayed delete of a file an import
 * replaced, which must never delete a file still in use when the import rolls back. The counterpart of
 * AfterCommitDispatcher for messages: on its own it sends at once, and an import holds what's sent while it runs, and
 * releases it once its transaction commits, or forgets it when it rolls back. See "What an import does" in
 * docs/spec/transfer.md
 */
final class AfterCommitMessageBus implements MessageBusInterface, ResetInterface
{
    /** @var list<Envelope>|null the messages held back, in order, or null while none are held */
    private ?array $held = null;

    /**
     * @param MessageBusInterface $messageBus the bus the messages go on, Sylius's event bus
     */
    public function __construct(private readonly MessageBusInterface $messageBus)
    {
    }

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        if (null === $this->held) {
            return $this->messageBus->dispatch($message, $stamps);
        }

        $envelope = Envelope::wrap($message, $stamps);
        $this->held[] = $envelope;

        return $envelope;
    }

    /**
     * Holds back every message sent from now on, until release() or forget()
     *
     * @throws \LogicException when messages are held already: an import doesn't run inside another
     */
    public function hold(): void
    {
        if (null !== $this->held) {
            throw new \LogicException('Messages are held already');
        }

        $this->held = [];
    }

    /**
     * Sends the messages held back, in the order they were sent, with their stamps, and stops holding them
     */
    public function release(): void
    {
        $held = $this->held ?? [];
        $this->held = null;

        foreach ($held as $envelope) {
            $this->messageBus->dispatch($envelope);
        }
    }

    /**
     * Forgets the messages held back, and stops holding them
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
