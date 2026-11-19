<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Repository\ImportRepositoryInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

/**
 * Fails an import started in the admin once Messenger gives up on its RunImport, because RunImportHandler kept throwing
 * an unexpected error and no retry is left: the import records the error and stops waiting, and its zip is deleted. A
 * refused import never gets here, as the handler records the refusal and returns. See "Who runs it" in
 * docs/spec/transfer.md
 */
final readonly class FailedImportListener
{
    /**
     * @param ObjectManager $manager the import resource's manager, which the failed run may have closed
     */
    public function __construct(
        private ImportRepositoryInterface $imports,
        private ImportQueueInterface $queue,
        private ObjectManager $manager,
        private ManagerRegistry $managerRegistry,
    ) {
    }

    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();
        if ($event->willRetry() || !$message instanceof RunImport) {
            return;
        }

        $this->reopenManager();

        $import = $this->imports->findOneByUuid($message->importUuid);
        if (null === $import || !$import->isWaiting()) {
            return;
        }

        $this->queue->fail($import, self::reason($event->getThrowable()));
    }

    /**
     * Doctrine closes the entity manager when a flush fails, and an import that failed that way can't be recorded in it
     */
    private function reopenManager(): void
    {
        if (!$this->manager instanceof EntityManagerInterface || $this->manager->isOpen()) {
            return;
        }

        $name = array_search($this->manager, $this->managerRegistry->getManagers(), true);
        $this->managerRegistry->resetManager(is_string($name) ? $name : null);
    }

    /**
     * What the handler threw, rather than Messenger's wrapper around it
     */
    private static function reason(\Throwable $throwable): string
    {
        if ($throwable instanceof HandlerFailedException) {
            $wrapped = $throwable->getWrappedExceptions();
            $throwable = [] === $wrapped ? $throwable : reset($wrapped);
        }

        return $throwable->getMessage();
    }
}
