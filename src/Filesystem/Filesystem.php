<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Filesystem;

use Gaufrette\Filesystem as BaseFilesystem;

final class Filesystem extends BaseFilesystem implements FilesystemInterface
{
    public function resolveUrl(string $key): string
    {
        $adapter = $this->getAdapter();
        if (!$adapter instanceof AdapterInterface) {
            throw new \RuntimeException(sprintf(
                'The adapter needs to implement the interface %s',
                AdapterInterface::class
            ));
        }

        return $adapter->resolveUrl($key);
    }
}
