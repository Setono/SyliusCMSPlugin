<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\EventSubscriber;

use Gaufrette\FilesystemInterface;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Setono\SyliusCMSPlugin\Model\AssetInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Throwable;

final class RemoveAssetFileSubscriber implements EventSubscriberInterface
{
    private FilesystemInterface $filesystem;

    private CacheManager $cacheManager;

    public function __construct(FilesystemInterface $filesystem, CacheManager $cacheManager)
    {
        $this->filesystem = $filesystem;
        $this->cacheManager = $cacheManager;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'setono_sylius_cms.asset.post_delete' => 'removeAssetFile',
        ];
    }

    public function removeAssetFile(GenericEvent $event): void
    {
        $asset = $event->getSubject();
        if (!$asset instanceof AssetInterface) {
            return;
        }

        $path = $asset->getPath();
        if (null === $path) {
            return;
        }

        // Note: this will remove the file, but not the folder, as performing all necessary checks would be way too much
        try {
            // Remove all caches for this asset
            $this->cacheManager->remove([$path]);
        } catch (Throwable $exception) {
        }

        try {
            // And remove the original file
            $this->filesystem->delete($path);
        } catch (Throwable $exception) {
        }
    }
}
