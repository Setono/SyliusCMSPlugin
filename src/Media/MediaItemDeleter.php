<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Setono\SyliusCMSPlugin\Doctrine\EntityManagers;
use Setono\SyliusCMSPlugin\Event\BeforeMediaItemDelete;
use Setono\SyliusCMSPlugin\Event\MediaItemDeleted;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Repository\MediaUsageRepositoryInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The check, the listeners and the delete run in one transaction. The file and its variants are deleted after it
 * commits, so a delete that rolls back keeps them. A file that can't be deleted then is logged and left, since its
 * media item is gone
 */
final readonly class MediaItemDeleter implements MediaItemDeleterInterface
{
    public function __construct(
        private ManagerRegistry $managerRegistry,
        private MediaUsageRepositoryInterface $usages,
        private EventDispatcherInterface $eventDispatcher,
        private MediaFileRemoverInterface $remover,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function delete(MediaItemInterface $mediaItem): void
    {
        $uuid = $mediaItem->getUuid();
        $path = $mediaItem->getPath();

        $manager = EntityManagers::of($this->managerRegistry, $mediaItem::class);
        $connection = $manager->getConnection();
        $connection->beginTransaction();

        try {
            $uses = $this->usages->findByMediaItem($uuid);
            if ([] !== $uses) {
                throw new MediaItemInUseException($uuid, $uses);
            }

            $veto = $this->eventDispatcher->dispatch(new BeforeMediaItemDelete($mediaItem))->getVetoMessage();
            if (null !== $veto) {
                throw new MediaItemDeleteVetoedException($veto);
            }

            $manager->remove($mediaItem);
            $manager->flush();
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();

            throw $e;
        }

        // After the transaction commits
        try {
            $this->remover->remove($path);
        } catch (\Throwable $e) {
            $this->logger->error('The file {path} of the deleted media item {uuid} couldn\'t be deleted: {message}', [
                'path' => $path,
                'uuid' => $uuid,
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);
        }

        $this->eventDispatcher->dispatch(new MediaItemDeleted($uuid));
    }
}
