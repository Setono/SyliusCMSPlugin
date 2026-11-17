<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

/**
 * Deletes a file from the plugin's storage together with the variants generated from it: the old file of a replaced
 * media item once its grace period is over, and the file of a deleted media item. The default deletes the file. Image
 * variants are deleted by decorating this service. See "Replacing a file" and "Deleting" in docs/spec/media.md
 */
interface MediaFileRemoverInterface
{
    /**
     * @param string $path the file's path in the plugin's storage. A file that isn't there is skipped
     */
    public function remove(string $path): void;
}
