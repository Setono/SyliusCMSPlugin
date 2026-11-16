<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Media\InvalidMediaChangeException;
use Setono\SyliusCMSPlugin\Model\MediaFolderInterface;
use Setono\SyliusCMSPlugin\Repository\MediaFolderRepositoryInterface;

/**
 * What the media folder endpoints share: a folder as they answer with it, and the folder a body names
 *
 * @internal
 */
final readonly class MediaFolderRequest
{
    public function __construct(private MediaFolderRepositoryInterface $folderRepository)
    {
    }

    /**
     * @return array{uuid: string, name: string, parent: string|null}
     */
    public static function answer(MediaFolderInterface $folder): array
    {
        return ['uuid' => $folder->getUuid(), 'name' => $folder->getName(), 'parent' => $folder->getParent()?->getUuid()];
    }

    /**
     * The folder a body names by its UUID, or null for the top of the library
     *
     * @throws InvalidMediaChangeException when there's no such folder
     */
    public function parent(mixed $uuid): ?MediaFolderInterface
    {
        if (null === $uuid) {
            return null;
        }

        $uuid = self::string($uuid, 'parent');

        return $this->folderRepository->findOneByUuid($uuid) ?? throw new InvalidMediaChangeException(sprintf('There is no media folder %s', $uuid));
    }

    /**
     * @throws InvalidMediaChangeException
     */
    public static function string(mixed $value, string $name): string
    {
        return is_string($value) ? $value : throw new InvalidMediaChangeException(sprintf('The %s must be a string', $name));
    }
}
