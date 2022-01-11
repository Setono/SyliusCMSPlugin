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

    private ElementCacheKeyGeneratorInterface $elementCacheKeyGenerator;

    private int $cacheTtl;

    /** @var class-string */
    private string $carouselClass;

    /**
     * @param class-string $carouselClass
     */
    public function __construct(
        CarouselRendererInterface $decoratedRenderer,
        CacheInterface $cachePool,
        ElementCacheKeyGeneratorInterface $elementCacheKeyGenerator,
        int $cacheTtl,
        string $carouselClass
    ) {
        $this->decoratedRenderer = $decoratedRenderer;
        $this->cachePool = $cachePool;
        $this->elementCacheKeyGenerator = $elementCacheKeyGenerator;
        $this->cacheTtl = $cacheTtl;
        $this->carouselClass = $carouselClass;
    }

    public function render($carousel): string
    {
        $cacheKey = $this->elementCacheKeyGenerator->generateCacheKey($carousel, $this->carouselClass);

        /** @psalm-suppress ArgumentTypeCoercion */
        return $this->cachePool->get($cacheKey, function (ItemInterface $item) use ($carousel): string {
            $item->expiresAfter($this->cacheTtl);

            return $this->decoratedRenderer->render($carousel);
        });
    }
}
