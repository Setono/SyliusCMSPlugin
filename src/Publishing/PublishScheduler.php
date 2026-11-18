<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

use Doctrine\Persistence\ObjectManager;
use Psr\Clock\ClockInterface;
use Setono\SyliusCMSPlugin\Event\EntryPublishCancelled;
use Setono\SyliusCMSPlugin\Event\EntryPublishFailed;
use Setono\SyliusCMSPlugin\Event\EntryPublishScheduled;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\ScheduledPublishInterface;
use Setono\SyliusCMSPlugin\Repository\ScheduledPublishRepositoryInterface;
use Setono\SyliusCMSPlugin\Security\EditorName;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Each change runs in a transaction with the entry's row locked, as autosave and publishing lock it, so a save can't
 * land between the checks and the lock, and a scheduled publish can't run halfway through a cancel
 */
final readonly class PublishScheduler implements PublishSchedulerInterface
{
    /**
     * @param FactoryInterface<ScheduledPublishInterface> $scheduledPublishFactory
     * @param ObjectManager $manager the manager of scheduled publishes, which is also the manager of entries
     * @param MessageBusInterface $messageBus the bus the delayed message goes on, Sylius's event bus, which has no
     *                                        transaction of its own around the handler
     */
    public function __construct(
        private EntryLock $entryLock,
        private WorkingCopyProviderInterface $workingCopies,
        private PublishCheck $check,
        private ScheduledPublishRepositoryInterface $scheduledPublishes,
        private FactoryInterface $scheduledPublishFactory,
        private ObjectManager $manager,
        private MessageBusInterface $messageBus,
        private EventDispatcherInterface $eventDispatcher,
        private ClockInterface $clock,
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public function schedule(EntryInterface $entry, \DateTimeImmutable $at): void
    {
        $now = $this->clock->now();
        if ($at <= $now) {
            throw new ScheduledPublishInThePastException($at);
        }

        $scheduledPublish = $this->entryLock->write($entry, function () use ($entry, $at, $now): ScheduledPublishInterface {
            $this->check->check($entry, $this->workingCopies->getWorkingCopy($entry), true);

            $scheduledPublish = $this->scheduledPublishes->findOneByEntry($entry);
            if (null === $scheduledPublish) {
                $scheduledPublish = $this->scheduledPublishFactory->createNew();
                $scheduledPublish->setEntry($entry);
                $this->manager->persist($scheduledPublish);
            }

            $scheduledPublish->schedule($at, EditorName::signedIn($this->tokenStorage));

            // Sent in the transaction, so a transport on the database sends it only if the scheduled publish is saved.
            // On any other, a message whose scheduled publish wasn't saved finds no scheduled publish with its token
            $this->messageBus->dispatch(
                new ScheduledPublishDue($entry->getUuid(), $scheduledPublish->getToken()),
                [new DelayStamp(($at->getTimestamp() - $now->getTimestamp()) * 1000)],
            );

            return $scheduledPublish;
        });

        $this->eventDispatcher->dispatch(new EntryPublishScheduled($entry->getUuid(), $scheduledPublish->getPublishAt()));
    }

    public function cancel(EntryInterface $entry): void
    {
        $cancelled = $this->entryLock->write($entry, function () use ($entry): bool {
            $scheduledPublish = $this->scheduledPublishes->findOneByEntry($entry);
            if (null === $scheduledPublish) {
                return false;
            }

            $this->manager->remove($scheduledPublish);

            return !$scheduledPublish->isFailed();
        });

        if ($cancelled) {
            $this->eventDispatcher->dispatch(new EntryPublishCancelled($entry->getUuid()));
        }
    }

    public function fail(EntryInterface $entry, string $token, string $reason): void
    {
        $failed = $this->entryLock->write($entry, function () use ($entry, $token, $reason): bool {
            $scheduledPublish = $this->scheduledPublishes->findOneByEntry($entry);
            if (null === $scheduledPublish || $scheduledPublish->isFailed() || $token !== $scheduledPublish->getToken()) {
                return false;
            }

            $scheduledPublish->fail($reason);

            return true;
        });

        if ($failed) {
            $this->eventDispatcher->dispatch(new EntryPublishFailed($entry->getUuid(), $reason));
        }
    }
}
