<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

use Setono\SyliusCMSPlugin\Media\CheckedFile;
use Setono\SyliusCMSPlugin\Transfer\Format\ExportedMediaItem;

/**
 * A media item of the export as the import read it: its JSON as it's written, and its file, checked as an upload is
 * and waiting in a temporary copy until the import stores it or is done. What the file is, its kind, MIME type, size,
 * dimensions and checksum, is what the check read from it, never what the JSON says
 */
final readonly class ReadMediaItem
{
    /**
     * @param bool $picked whether the export picked it, rather than it coming along as a dependency
     * @param string $copy the temporary copy of the file the export holds, which cleanUp() removes
     */
    public function __construct(
        public ExportedMediaItem $exported,
        public CheckedFile $file,
        public bool $picked,
        private string $copy,
    ) {
    }

    public function uuid(): string
    {
        return $this->exported->uuid;
    }

    /**
     * What names it: its name
     */
    public function label(): string
    {
        return $this->exported->name;
    }

    /**
     * Removes the temporary files of its file, once the import stored it or is done
     */
    public function cleanUp(): void
    {
        $this->file->cleanUp();
        if (is_file($this->copy)) {
            unlink($this->copy);
        }
    }
}
