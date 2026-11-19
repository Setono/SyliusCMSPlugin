<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Format;

use Setono\SyliusCMSPlugin\Model\MediaFolderInterface;

/**
 * A media folder in an export's media/folders.json. See "media/folders.json" in docs/spec/transfer.md
 */
final readonly class ExportedMediaFolder
{
    /**
     * @param string|null $parent the parent folder's UUID, or null for a folder at the top
     */
    public function __construct(
        public string $uuid,
        public string $name,
        public ?string $parent,
    ) {
    }

    public static function of(MediaFolderInterface $folder): self
    {
        return new self($folder->getUuid(), $folder->getName(), $folder->getParent()?->getUuid());
    }
}
