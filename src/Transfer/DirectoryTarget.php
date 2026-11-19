<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer;

/**
 * Writes an export into a folder on disk, making the folders its files sit in
 */
final readonly class DirectoryTarget implements ExportTargetInterface
{
    public function __construct(private string $directory)
    {
    }

    public function write(string $path, string $contents): void
    {
        if (false === file_put_contents($this->file($path), $contents)) {
            throw new \RuntimeException(sprintf('The file %s couldn\'t be written', $this->file($path)));
        }
    }

    public function writeStream(string $path, $stream): void
    {
        $file = fopen($this->file($path), 'wb');
        if (false === $file) {
            throw new \RuntimeException(sprintf('The file %s couldn\'t be written', $this->file($path)));
        }

        try {
            if (false === stream_copy_to_stream($stream, $file)) {
                throw new \RuntimeException(sprintf('The file %s couldn\'t be written', $this->file($path)));
            }
        } finally {
            fclose($file);
        }
    }

    /**
     * The file's path on disk, with the folder it sits in made
     */
    private function file(string $path): string
    {
        $file = rtrim($this->directory, '/') . '/' . $path;
        $directory = dirname($file);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('The directory %s couldn\'t be made', $directory));
        }

        return $file;
    }
}
