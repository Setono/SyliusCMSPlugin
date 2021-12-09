<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Doctrine\EntityListener;

use Doctrine\Persistence\Event\LifecycleEventArgs;
use Psr\Cache\InvalidArgumentException;
use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Setono\SyliusCMSPlugin\Provider\CmsElementCacheKeyProviderInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Contracts\Cache\CacheInterface;

final class CmsElementCacheInvalidatorListener
{
    private CacheInterface $cachePool;

    private CmsElementCacheKeyProviderInterface $cmsElementCacheKeyProvider;

    public function __construct(
        CacheInterface $cachePool,
        CmsElementCacheKeyProviderInterface $cmsElementCacheKeyProvider
    ) {
        $this->cachePool = $cachePool;
        $this->cmsElementCacheKeyProvider = $cmsElementCacheKeyProvider;
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof ElementInterface) {
            return;
        }

        $this->invalidateCache($entity);
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof ElementInterface) {
            return;
        }

        $this->invalidateCache($entity);
    }

    private function invalidateCache(ElementInterface $element): void
    {
        $cacheKey = $this->cmsElementCacheKeyProvider->getCacheKey($element);
        try {
            $this->cachePool->delete($cacheKey);
        } catch (InvalidArgumentException $e) {
            // Ignore because it means the cache does not exist yet
        }
    }
}
