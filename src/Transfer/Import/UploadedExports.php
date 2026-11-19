<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;

/**
 * The export zips uploaded on the Transfer screen, kept in the plugin's media storage rather than a temporary file, so a
 * Messenger worker on another server can read them. Each is kept private, under a random path in "imports", since the
 * default storage is a public directory. An import reads one from a local copy, as Transfer\Import\ZipSource reads
 * files, and deletes it once it finishes or is abandoned. See "Who runs it" in docs/spec/transfer.md
 */
final readonly class UploadedExports
{
    /** The directory of the storage they're kept in */
    public const DIRECTORY = 'imports';

    /**
     * @param FilesystemOperator $storage the plugin's media storage
     */
    public function __construct(
        private FilesystemOperator $storage,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Keeps the zip in the storage
     *
     * @return string its path in the storage
     *
     * @throws FilesystemException
     */
    public function keep(\SplFileInfo $file): string
    {
        $path = sprintf('%s/%s.zip', self::DIRECTORY, bin2hex(random_bytes(16)));

        $stream = fopen($file->getPathname(), 'rb');
        if (false === $stream) {
            throw new \RuntimeException(sprintf('The uploaded file %s couldn\'t be read', $file->getPathname()));
        }

        try {
            $this->storage->writeStream($path, $stream, ['visibility' => 'private']);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $path;
    }

    /**
     * Whether the storage holds it still
     *
     * @throws FilesystemException
     */
    public function has(string $path): bool
    {
        return $this->storage->fileExists($path);
    }

    /**
     * Reads a kept zip from a local copy, which is removed once $read returns
     *
     * @template T
     *
     * @param callable(string): T $read given the local copy's path
     *
     * @return T
     *
     * @throws FilesystemException
     */
    public function read(string $path, callable $read): mixed
    {
        $copy = tempnam(sys_get_temp_dir(), 'setono_sylius_cms_import_');
        if (false === $copy) {
            throw new \RuntimeException('A temporary file for the uploaded export couldn\'t be made');
        }

        try {
            $this->copy($path, $copy);

            return $read($copy);
        } finally {
            if (is_file($copy)) {
                unlink($copy);
            }
        }
    }

    /**
     * Deletes a kept zip. One the storage fails to delete is logged and left behind, as the import it belonged to is done
     */
    public function delete(string $path): void
    {
        try {
            $this->storage->delete($path);
        } catch (FilesystemException $e) {
            $this->logger->warning(sprintf('The uploaded export %s couldn\'t be deleted from the media storage: %s', $path, $e->getMessage()), ['exception' => $e]);
        }
    }

    /**
     * @throws FilesystemException
     */
    private function copy(string $path, string $copy): void
    {
        $from = $this->storage->readStream($path);
        $to = fopen($copy, 'wb');

        try {
            if (false === $to || false === stream_copy_to_stream($from, $to)) {
                throw new \RuntimeException(sprintf('The uploaded export %s couldn\'t be copied to %s', $path, $copy));
            }
        } finally {
            if (is_resource($from)) {
                fclose($from);
            }

            if (is_resource($to)) {
                fclose($to);
            }
        }
    }
}
