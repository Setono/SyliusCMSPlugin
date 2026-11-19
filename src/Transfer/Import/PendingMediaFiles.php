<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

use League\Flysystem\FilesystemOperator;
use Setono\SyliusCMSPlugin\Media\CheckedFile;

/**
 * The media files an import writes, each at the storage path its media item now points at. The import stores them only
 * once its plan is complete, right before its transaction commits, and removes them again when it can't commit, so a
 * refused import, a dry run or one that fails writes no file, and a committed one never points at a missing file. See
 * "What an import does" in docs/spec/transfer.md
 *
 * @internal
 */
final class PendingMediaFiles
{
    /** @var list<array{CheckedFile, string}> each file and its path in the storage */
    private array $files = [];

    /** @var list<string> the paths it stored */
    private array $stored = [];

    public function add(CheckedFile $file, string $path): void
    {
        $this->files[] = [$file, $path];
    }

    /**
     * @return list<string>
     */
    public function paths(): array
    {
        return array_map(static fn (array $file): string => $file[1], $this->files);
    }

    /**
     * Writes every file to the storage. When one can't be written, it removes those it wrote and throws
     *
     * @throws \League\Flysystem\FilesystemException
     */
    public function store(FilesystemOperator $storage): void
    {
        try {
            foreach ($this->files as [$file, $path]) {
                $file->store($storage, $path);
                $this->stored[] = $path;
            }
        } catch (\Throwable $e) {
            $this->remove($storage);

            throw $e;
        }
    }

    /**
     * Removes the files it stored, when the import can't commit after all
     */
    public function remove(FilesystemOperator $storage): void
    {
        foreach ($this->stored as $path) {
            $storage->delete($path);
        }

        $this->stored = [];
    }
}
