<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;
use League\Flysystem\FilesystemOperator;
use Psr\Clock\ClockInterface;
use Setono\SyliusCMSPlugin\Doctrine\EntityManagers;
use Setono\SyliusCMSPlugin\Event\MediaItemReplaced;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Stores the new file first, then points the media item at it in a transaction with the media item's row locked, so
 * two replaces of one media item each delete the file the other replaced. A new file whose media item couldn't be saved
 * is deleted again, and the old one is kept. The old file is deleted only by a message sent after the transaction
 * commits, so a replace that rolls back never deletes the file that's still in use
 */
final readonly class MediaFileReplacer implements MediaFileReplacerInterface
{
    /**
     * @param MessageBusInterface $messageBus the bus the delayed message goes on, Sylius's event bus
     */
    public function __construct(
        private MediaFileCheckerInterface $checker,
        private FilesystemOperator $storage,
        private ManagerRegistry $managerRegistry,
        private MessageBusInterface $messageBus,
        private EventDispatcherInterface $eventDispatcher,
        private ClockInterface $clock,
        private GracePeriod $gracePeriod,
    ) {
    }

    public function replace(MediaItemInterface $mediaItem, \SplFileInfo $file, string $originalFilename): void
    {
        $originalFilename = UploadedFilename::clean($originalFilename);
        $checked = $this->checker->check($file, $originalFilename);

        try {
            if ($checked->kind !== $mediaItem->getKind()) {
                throw RefusedFileException::otherKind($originalFilename, $mediaItem->getKind());
            }

            $path = StoragePath::for($mediaItem->getUuid(), $originalFilename, $checked->extension);
            $checked->store($this->storage, $path);
        } finally {
            $checked->cleanUp();
        }

        try {
            $oldPath = $this->point($mediaItem, $checked, $originalFilename, $path);
        } catch (\Throwable $e) {
            $this->storage->delete($path);

            throw $e;
        }

        // After the transaction commits
        $this->messageBus->dispatch(
            new DeleteReplacedFile($mediaItem->getUuid(), $oldPath),
            [new DelayStamp($this->gracePeriod->delayInMilliseconds($this->clock->now()))],
        );
        $this->eventDispatcher->dispatch(new MediaItemReplaced($mediaItem->getUuid()));
    }

    /**
     * Points the media item at the new file, and gives the path of the file it had
     */
    private function point(MediaItemInterface $mediaItem, CheckedFile $checked, string $originalFilename, string $path): string
    {
        $manager = EntityManagers::of($this->managerRegistry, $mediaItem::class);
        $connection = $manager->getConnection();
        $connection->beginTransaction();

        try {
            $manager->lock($mediaItem, LockMode::PESSIMISTIC_WRITE);
            $manager->refresh($mediaItem);
            $oldPath = $mediaItem->getPath();

            $checked->applyTo($mediaItem, $originalFilename, $path);
            $mediaItem->setChangedAt($this->clock->now());

            $manager->flush();
            $connection->commit();

            return $oldPath;
        } catch (\Throwable $e) {
            $connection->rollBack();

            throw $e;
        }
    }
}
