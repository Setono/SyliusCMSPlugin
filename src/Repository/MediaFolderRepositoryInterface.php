<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Setono\SyliusCMSPlugin\Model\MediaFolderInterface;

/**
 * The folders of the media library. See "Folders" in docs/spec/media.md
 */
interface MediaFolderRepositoryInterface
{
    /**
     * Stores the folder at once
     */
    public function add(MediaFolderInterface $resource): void;

    public function findOneByUuid(string $uuid): ?MediaFolderInterface;

    /**
     * Every folder, by name, so the library builds its tree from them
     *
     * @return list<MediaFolderInterface>
     */
    public function findAllByName(): array;
}
