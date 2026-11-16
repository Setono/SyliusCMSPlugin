<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\ScheduledPublishInterface;
use Setono\SyliusCMSPlugin\Repository\ScheduledPublishRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

/**
 * Helpers for functional and UI tests of scheduled publishes. In the tests, Sylius's main transport is kept in memory
 * (KeptInMemoryTransportFactory), so a test reads the delayed messages that scheduling sent, and runs them as the
 * Messenger worker would, without waiting for their delay
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
     * The messages sent to Sylius's main transport and not consumed yet
     *
     * @return list<Envelope>
     */
    protected static function sentMessages(): array
    {
        return array_values([...KeptInMemoryTransportFactory::transport('main')->get()]);
    }

    /**
     * Runs the messages sent to Sylius's main transport, on the bus each was sent on, as the Messenger worker does when
     * their time comes, and acknowledges them
     */
    protected static function consumeMessages(): void
    {
        $transport = KeptInMemoryTransportFactory::transport('main');
        foreach (self::sentMessages() as $envelope) {
            $busName = $envelope->last(BusNameStamp::class)?->getBusName();
            self::assertNotNull($busName, 'A message is sent on a bus');

            $bus = self::getContainer()->get($busName);
            self::assertInstanceOf(MessageBusInterface::class, $bus);

            $bus->dispatch($envelope->with(new ReceivedStamp('main'), new ConsumedByWorkerStamp()));
            $transport->ack($envelope);
        }
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
        /** @var EntryInterface|null $reloaded */
        $reloaded = $manager->getRepository($entry::class)->findOneBy(['uuid' => $entry->getUuid()]);
        self::assertNotNull($reloaded);

        return $scheduledPublishes->findOneByEntry($reloaded);
    }
}
