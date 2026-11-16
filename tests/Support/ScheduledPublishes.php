<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\ScheduledPublishInterface;
use Setono\SyliusCMSPlugin\Repository\ScheduledPublishRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Worker;

/**
 * Helpers for functional and UI tests of scheduled publishes. In the tests, Sylius's main transport is kept in memory
 * (KeptInMemoryTransportFactory), and a delayed message becomes available by the clock, which tests mock. So a test
 * reads the messages that scheduling sent, moves the clock on, and runs a Messenger worker on what's due
 */
trait ScheduledPublishes
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

    /**
     * The entry's scheduled publish as the database holds it, or null when it has none
     */
    protected static function scheduledPublishOf(EntryInterface $entry): ?ScheduledPublishInterface
    {
        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.scheduled_publish');
        $manager->clear();

        /** @var ScheduledPublishRepositoryInterface $scheduledPublishes */
        $scheduledPublishes = self::getContainer()->get('setono_sylius_cms.repository.scheduled_publish');
        $reloaded = $manager->find($entry::class, $entry->getId());
        self::assertInstanceOf(EntryInterface::class, $reloaded);

        return $scheduledPublishes->findOneByEntry($reloaded);
    }
}
