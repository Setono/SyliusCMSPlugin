<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Renderer;

use Setono\SyliusCMSPlugin\Generator\ElementCacheKeyGeneratorInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class CachedCarouselRenderer implements CarouselRendererInterface
{
    private CarouselRendererInterface $decoratedRenderer;

    private CacheInterface $cachePool;

    private ElementCacheKeyGeneratorInterface $cmsElementCacheKeyProvider;

    private int $cacheTtl;

    private string $carouselClass;

    public function __construct(
        CarouselRendererInterface $decoratedRenderer,
        CacheInterface $cachePool,
        ElementCacheKeyGeneratorInterface $cmsElementCacheKeyProvider,
        int $cacheTtl,
        string $carouselClass
    ) {
        $this->decoratedRenderer = $decoratedRenderer;
        $this->cachePool = $cachePool;
        $this->cmsElementCacheKeyProvider = $cmsElementCacheKeyProvider;
        $this->cacheTtl = $cacheTtl;
        $this->carouselClass = $carouselClass;
    }

    public function render($carousel): string
    {
        $cacheKey = $this->cmsElementCacheKeyProvider->getCacheKey($carousel, $this->carouselClass);

        return $this->cachePool->get($cacheKey, function (ItemInterface $item) use ($carousel): string {
            $item->expiresAfter($this->cacheTtl);

            return $this->decoratedRenderer->render($carousel);
        });
    }
}
