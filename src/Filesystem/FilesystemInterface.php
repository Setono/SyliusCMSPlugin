<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Filesystem;

use Gaufrette\FilesystemInterface as BaseFilesystemInterface;

interface FilesystemInterface extends BaseFilesystemInterface
{
    public function resolveUrl(string $key): string;
}
