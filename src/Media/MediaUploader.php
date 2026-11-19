<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

use League\Flysystem\FilesystemOperator;
use Psr\Clock\ClockInterface;
use Setono\SyliusCMSPlugin\Model\MediaFolderInterface;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Repository\MediaItemRepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;

/**
 * Stores a checked file in the plugin's storage under the media item's UUID, then the media item. A file whose media
 * item couldn't be stored is deleted again
 */
final readonly class MediaUploader implements MediaUploaderInterface
{
    /**
     * @param FactoryInterface<MediaItemInterface> $mediaItemFactory
     */
    public function __construct(
        private MediaFileCheckerInterface $checker,
        private MediaItemRepositoryInterface $mediaItemRepository,
        private FactoryInterface $mediaItemFactory,
        private FilesystemOperator $storage,
        private ClockInterface $clock,
    ) {
    }

    public function upload(\SplFileInfo $file, string $originalFilename, ?MediaFolderInterface $folder, bool $copy = false): UploadResult
    {
        $originalFilename = UploadedFilename::clean($originalFilename);
        $checked = $this->checker->check($file, $originalFilename);

        try {
            $duplicate = $copy ? null : $this->mediaItemRepository->findOneByChecksum($checked->checksum);
            if (null !== $duplicate) {
                return UploadResult::duplicateOf($duplicate);
            }

            $mediaItem = $this->mediaItemFactory->createNew();
            $mediaItem->setKind($checked->kind);
            $mediaItem->setFolder($folder);
            $mediaItem->setName(self::name($originalFilename));
            $checked->applyTo($mediaItem, $originalFilename, StoragePath::for($mediaItem->getUuid(), $originalFilename, $checked->extension));
            $mediaItem->setChangedAt($this->clock->now());

            $checked->store($this->storage, $mediaItem->getPath());

            try {
                $this->mediaItemRepository->add($mediaItem);
            } catch (\Throwable $e) {
                $this->storage->delete($mediaItem->getPath());

                throw $e;
            }

            return UploadResult::created($mediaItem);
        } finally {
            $checked->cleanUp();
        }
    }

    /**
     * The original filename without its extension, or the whole of it when that leaves nothing, such as for ".htaccess"
     */
    private static function name(string $filename): string
    {
        $name = pathinfo($filename, \PATHINFO_FILENAME);

        return '' === trim($name) ? $filename : $name;
    }
}
