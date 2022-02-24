<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\EventListener\Doctrine;

use Doctrine\Persistence\Event\LifecycleEventArgs;
use Setono\SyliusCMSPlugin\Generator\ElementCacheKeyGeneratorInterface;
use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Contracts\Cache\CacheInterface;

final class GenericElementCacheInvalidatorListener
{
    private CacheInterface $cachePool;

    private ElementCacheKeyGeneratorInterface $elementCacheKeyProvider;
    private EventDispatcherInterface $eventDispatcher;

    public function __construct(
        CacheInterface $cachePool,
        ElementCacheKeyGeneratorInterface $elementCacheKeyProvider,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->cachePool = $cachePool;
        $this->elementCacheKeyProvider = $elementCacheKeyProvider;
        $this->eventDispatcher = $eventDispatcher;
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
        $cacheKey = $this->elementCacheKeyProvider->generateCacheKey($element, get_class($element));

        try {
            $this->cachePool->delete($cacheKey);
        } catch (\Throwable $e) {
            // Ignore because it means the cache does not exist yet
        }

        $this->eventDispatcher->dispatch(new GenericEvent($element), 'setono_sylius_cms.block.cache_invalidated');
    }
}
