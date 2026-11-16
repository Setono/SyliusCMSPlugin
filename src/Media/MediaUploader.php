<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

use League\Flysystem\FilesystemOperator;
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
    private const MAX_FILENAME_LENGTH = 255;

    /**
     * @param FactoryInterface<MediaItemInterface> $mediaItemFactory
     */
    public function __construct(
        private MediaFileCheckerInterface $checker,
        private MediaItemRepositoryInterface $mediaItemRepository,
        private FactoryInterface $mediaItemFactory,
        private FilesystemOperator $storage,
    ) {
    }

    public function upload(\SplFileInfo $file, string $originalFilename, ?MediaFolderInterface $folder, bool $copy = false): UploadResult
    {
        $originalFilename = self::filename($originalFilename);
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
            $mediaItem->setOriginalFilename($originalFilename);
            $mediaItem->setMimeType($checked->mimeType);
            $mediaItem->setSize($checked->size);
            $mediaItem->setDimensions($checked->width, $checked->height);
            $mediaItem->setChecksum($checked->checksum);
            $mediaItem->setPath(StoragePath::for($mediaItem->getUuid(), $originalFilename, $checked->extension));

            $this->store($checked, $mediaItem->getPath());

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

    private function store(CheckedFile $checked, string $path): void
    {
        $stream = fopen($checked->path, 'rb');
        if (false === $stream) {
            throw new \RuntimeException(sprintf('The checked file %s couldn\'t be read', $checked->path));
        }

        try {
            $this->storage->writeStream($path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /**
     * The name a browser sent, without a path, and short enough for the column
     */
    private static function filename(string $filename): string
    {
        $filename = trim(basename(str_replace('\\', '/', $filename)));

        return mb_substr('' === $filename ? 'file' : $filename, -self::MAX_FILENAME_LENGTH);
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
