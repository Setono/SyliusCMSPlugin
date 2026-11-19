<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Resource\Model\ResourceInterface;

/**
 * A folder of the media library. Folders nest, and exist only in the library: moving a media item or renaming a folder
 * never moves a file or changes a URL. See "Folders" in docs/spec/media.md
 */
interface MediaFolderInterface extends ResourceInterface
{
    public function getId(): ?int;

    public function getUuid(): string;

    /**
     * For a folder an import creates, which keeps the UUID it has in the export (docs/spec/transfer.md)
     */
    public function setUuid(string $uuid): void;

    public function getName(): string;

    public function setName(string $name): void;

    /**
     * The folder it's in, or null at the top of the library
     */
    public function getParent(): ?self;

    public function setParent(?self $parent): void;

    /**
     * Whether $folder is this folder or a folder inside it, at any depth, which this folder can't be moved into
     */
    public function contains(self $folder): bool;
}
