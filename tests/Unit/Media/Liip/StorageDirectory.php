<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Media\Liip;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;

/**
 * A media storage like the plugin's default, a local directory served at /media/cms, in a directory of the test's own
 * that's removed after the test
 */
trait StorageDirectory
{
    private ?string $storageDirectory = null;

    /**
     * @after
     */
    protected function removeStorageDirectory(): void
    {
        if (null === $this->storageDirectory || !is_dir($this->storageDirectory)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->storageDirectory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            \assert($file instanceof \SplFileInfo);
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($this->storageDirectory);
        $this->storageDirectory = null;
    }

    private function storage(): FilesystemOperator
    {
        $this->storageDirectory ??= sys_get_temp_dir() . '/setono-cms-storage-' . bin2hex(random_bytes(6));

        return new Filesystem(new LocalFilesystemAdapter($this->storageDirectory), ['public_url' => '/media/cms']);
    }
}
