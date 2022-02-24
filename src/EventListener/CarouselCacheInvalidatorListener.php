<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\EventListener;

use Setono\SyliusCMSPlugin\Generator\ElementCacheKeyGeneratorInterface;
use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Setono\SyliusCMSPlugin\Repository\CarouselRepositoryInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Contracts\Cache\CacheInterface;

class CarouselCacheInvalidatorListener
{
    private CacheInterface $cachePool;
    private ElementCacheKeyGeneratorInterface $elementCacheKeyProvider;
    private CarouselRepositoryInterface $repository;

    public function __construct(
        CacheInterface $cachePool,
        ElementCacheKeyGeneratorInterface $elementCacheKeyProvider,
        CarouselRepositoryInterface $repository
    )
    {
        $this->cachePool = $cachePool;
        $this->elementCacheKeyProvider = $elementCacheKeyProvider;
        $this->repository = $repository;
    }

    public function invalidateCache(GenericEvent $event): void
    {
        /** @var ElementInterface $block */
        $element = $event->getSubject();

        $carousels = $this->repository->findByBlock($element);

        if (count($carousels) > 0) {
            foreach ($carousels as $carousel) {
                $cacheKey = $this->elementCacheKeyProvider->generateCacheKey($carousel, get_class($carousel));
                try {
                    $this->cachePool->delete($cacheKey);
                } catch (\Throwable $e) {
                    // Ignore because it means the cache does not exist yet
                }
            }
        }
    }
}
