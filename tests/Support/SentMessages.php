<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Worker;

/**
 * Helpers for functional and UI tests of delayed Messenger messages, such as a scheduled publish or the delete of a
 * replaced file. In the tests, Sylius's main transport is kept in memory (KeptInMemoryTransportFactory), and a delayed
 * message becomes available by the clock, which tests mock. So a test reads the messages that were sent, moves the
 * clock on, and runs a Messenger worker on what's due
 */
trait SentMessages
{
    abstract protected static function getContainer(): ContainerInterface;

    /**
     * Forgets the messages sent to Sylius's main transport before, such as by the tests before this one
     */
    protected static function forgetSentMessages(): void
    {
        KeptInMemoryTransportFactory::clear();
    }

    /**
     * Every message sent to Sylius's main transport since it was last forgotten, consumed or not
     *
     * @return list<Envelope>
     */
    protected static function sentMessages(): array
    {
        return array_values(KeptInMemoryTransportFactory::transport('main')->getSent());
    }

    /**
     * Runs a Messenger worker on Sylius's main transport until it has handled the messages that are due by the clock,
     * on the bus each was sent on, as a project's worker does. It has a dispatcher of its own, so what one run adds to it,
     * such as when to stop, doesn't stay for the next
     *
     * @return int how many messages it handled
     */
    protected static function consumeMessages(): int
    {
        $transport = KeptInMemoryTransportFactory::transport('main');
        $due = count([...$transport->get()]);
        if (0 === $due) {
            return 0;
        }

        $bus = self::getContainer()->get('messenger.routable_message_bus');
        self::assertInstanceOf(MessageBusInterface::class, $bus);

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new StopWorkerOnMessageLimitListener($due));
        (new Worker(['main' => $transport], $bus, $eventDispatcher))->run(['sleep' => 0]);

        return $due;
    }

    /**
     * The messages a worker acknowledged, which it handled without an error, and so doesn't retry. One it couldn't
     * handle is rejected, or sent again to retry
     *
     * @return list<Envelope>
     */
    protected static function acknowledgedMessages(): array
    {
        return array_values(KeptInMemoryTransportFactory::transport('main')->getAcknowledged());
    }
}
