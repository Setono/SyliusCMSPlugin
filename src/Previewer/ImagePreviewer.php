<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Previewer;

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Setono\SyliusCMSPlugin\Model\AssetInterface;

final class ImagePreviewer implements PreviewerInterface
{
    private CacheManager $cacheManager;

    public function __construct(CacheManager $cacheManager)
    {
        $this->cacheManager = $cacheManager;
    }

    public function preview(AssetInterface $asset): Preview
    {
        return new Preview(sprintf(
            '<img src="%s" alt="%s" style="width: 100%%">',
            $this->cacheManager->getBrowserPath((string) $asset->getPath(), 'setono_sylius_cms_asset', [], 'setono_sylius_cms_asset'),
            (string) $asset->getName()
        ));
    }

    public function supports(AssetInterface $asset): bool
    {
        $mimeType = $asset->getMimeType();
        if (null === $mimeType) {
            return false;
        }

        return strpos($mimeType, 'image') !== false;
    }
}
