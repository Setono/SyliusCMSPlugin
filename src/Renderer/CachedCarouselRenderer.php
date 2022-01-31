<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Renderer;

use Setono\SyliusCMSPlugin\Generator\ElementCacheKeyGeneratorInterface;
use Setono\SyliusCMSPlugin\Model\CarouselInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @implements RendererInterface<CarouselInterface>
 */
final class CachedCarouselRenderer implements RendererInterface
{
    /** @var RendererInterface<CarouselInterface> */
    private RendererInterface $decoratedRenderer;

    private CacheInterface $cachePool;

    private ElementCacheKeyGeneratorInterface $elementCacheKeyGenerator;

    private int $cacheTtl;

    /** @var class-string */
    private string $carouselClass;

    /**
     * @param RendererInterface<CarouselInterface> $decoratedRenderer
     * @param class-string $carouselClass
     */
    public function __construct(
        RendererInterface $decoratedRenderer,
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

    public function render($element): Response
    {
        $cacheKey = $this->elementCacheKeyGenerator->generateCacheKey($element, $this->carouselClass);

        /** @psalm-suppress ArgumentTypeCoercion */
        return $this->cachePool->get($cacheKey, function (ItemInterface $item) use ($element): Response {
            $item->expiresAfter($this->cacheTtl);

            return $this->decoratedRenderer->render($element);
        });
    }
}
