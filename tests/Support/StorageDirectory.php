<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;

/**
 * A media storage like the plugin's default, a local directory served at /media/cms, in the test's temporary directory
 */
trait StorageDirectory
{
    use TemporaryDirectory;

    private function storage(): FilesystemOperator
    {
        return new Filesystem(new LocalFilesystemAdapter($this->temporaryDirectory()), ['public_url' => '/media/cms']);
    }
}
