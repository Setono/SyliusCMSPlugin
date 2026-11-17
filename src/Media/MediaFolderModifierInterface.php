<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

use Setono\SyliusCMSPlugin\Model\MediaFolderInterface;

/**
 * Creates, renames and moves the folders of the media library. Folders exist only in the library, so none of it moves a
 * file or changes a URL. See "Folders" in docs/spec/media.md
 */
interface MediaFolderModifierInterface
{
    /**
     * @param MediaFolderInterface|null $parent the folder it goes in, or null for the top of the library
     *
     * @throws InvalidMediaChangeException
     */
    public function create(string $name, ?MediaFolderInterface $parent): MediaFolderInterface;

    /**
     * @throws InvalidMediaChangeException
     */
    public function rename(MediaFolderInterface $folder, string $name): void;

    /**
     * @param MediaFolderInterface|null $parent the folder it goes in, or null for the top of the library
     *
     * @throws InvalidMediaChangeException when $parent is the folder or a folder inside it
     */
    public function move(MediaFolderInterface $folder, ?MediaFolderInterface $parent): void;
}
