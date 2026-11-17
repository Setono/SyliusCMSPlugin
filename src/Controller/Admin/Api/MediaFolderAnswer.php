<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Model\MediaFolderInterface;

/**
 * A folder of the media library as the media folder endpoints answer with it
 *
 * @internal
 */
final class MediaFolderAnswer
{
    private function __construct()
    {
    }

    /**
     * @return array{uuid: string, name: string, parent: string|null}
     */
    public static function of(MediaFolderInterface $folder): array
    {
        return ['uuid' => $folder->getUuid(), 'name' => $folder->getName(), 'parent' => $folder->getParent()?->getUuid()];
    }
}
