<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer;

/**
 * Writes an export into a zip, holding the export's folder at its root. The zip is complete once it's closed. A file
 * from a stream is copied to a temporary file first, since a zip reads the files added to it only when it's closed, so
 * large media files never sit in memory
 */
final class ZipTarget implements ExportTargetInterface
{
    private readonly \ZipArchive $zip;

    /** @var list<string> */
    private array $temporaryFiles = [];

    /**
     * @throws \RuntimeException when the zip can't be made, such as when the file exists already
     */
    public function __construct(private readonly string $file)
    {
        $this->zip = new \ZipArchive();
        $opened = $this->zip->open($file, \ZipArchive::CREATE | \ZipArchive::EXCL);
        if (true !== $opened) {
            throw new \RuntimeException(sprintf('The zip %s couldn\'t be made (error %d)', $file, $opened));
        }
    }

    public function write(string $path, string $contents): void
    {
        if (!$this->zip->addFromString($path, $contents)) {
            throw new \RuntimeException(sprintf('%s couldn\'t be added to the zip %s', $path, $this->file));
        }
    }

    public function writeStream(string $path, $stream): void
    {
        $temporaryFile = tempnam(sys_get_temp_dir(), 'setono_sylius_cms_export_');
        if (false === $temporaryFile) {
            throw new \RuntimeException('A temporary file couldn\'t be made');
        }

        $this->temporaryFiles[] = $temporaryFile;
        (new DirectoryTarget(dirname($temporaryFile)))->writeStream(basename($temporaryFile), $stream);

        if (!$this->zip->addFile($temporaryFile, $path)) {
            throw new \RuntimeException(sprintf('%s couldn\'t be added to the zip %s', $path, $this->file));
        }
    }

    /**
     * Writes the zip and removes the temporary files
     */
    public function close(): void
    {
        try {
            if (!$this->zip->close()) {
                throw new \RuntimeException(sprintf('The zip %s couldn\'t be written', $this->file));
            }
        } finally {
            foreach ($this->temporaryFiles as $temporaryFile) {
                if (is_file($temporaryFile)) {
                    unlink($temporaryFile);
                }
            }

            $this->temporaryFiles = [];
        }
    }
}
