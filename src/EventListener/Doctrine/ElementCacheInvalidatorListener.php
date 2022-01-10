<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\EventListener\Doctrine;

use Doctrine\Persistence\Event\LifecycleEventArgs;
use Setono\SyliusCMSPlugin\Generator\ElementCacheKeyGeneratorInterface;
use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Symfony\Contracts\Cache\CacheInterface;

final class ElementCacheInvalidatorListener
{
    private CacheInterface $cachePool;

    private ElementCacheKeyGeneratorInterface $elementCacheKeyProvider;

    public function __construct(
        CacheInterface $cachePool,
        ElementCacheKeyGeneratorInterface $elementCacheKeyProvider
    ) {
        $this->cachePool = $cachePool;
        $this->elementCacheKeyProvider = $elementCacheKeyProvider;
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
        $cacheKey = $this->elementCacheKeyProvider->getCacheKey($element);

        try {
            $this->cachePool->delete($cacheKey);
        } catch (\Throwable $e) {
            // Ignore because it means the cache does not exist yet
        }
    }
}
