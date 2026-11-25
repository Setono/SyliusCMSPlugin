<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

use Setono\SyliusCMSPlugin\Transfer\Format\InvalidExportException;

/**
 * Copies a file of an export into a stream, as a folder and a zip both extract a media item's original file
 *
 * @internal
 */
final class ExportFileCopy
{
    /**
     * Copies no more than $length bytes of the file's stream into the target, and closes the file's stream
     *
     * @param resource|false $stream the file's, or false when it couldn't be opened
     * @param resource $target the caller opens and closes it
     * @param string $file the file, as the messages name it, such as media/<uuid>/lookbook.mp4 in export.zip
     *
     * @return int how many bytes it copied
     *
     * @throws InvalidExportException when the file couldn't be opened or read
     */
    public static function copy($stream, $target, int $length, string $file): int
    {
        if (false === $stream) {
            throw new InvalidExportException(sprintf('The export has no readable file %s', $file));
        }

        try {
            // libzip fails a read that reaches the end of a file that doesn't match its checksum, with a warning
            $copied = @stream_copy_to_stream($stream, $target, $length);
        } finally {
            fclose($stream);
        }

        return false === $copied ? throw new InvalidExportException(sprintf('The export\'s file %s can\'t be read', $file)) : $copied;
    }
}
