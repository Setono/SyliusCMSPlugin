<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Renderer;

use Setono\SyliusCMSPlugin\Generator\ElementCacheKeyGeneratorInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class CachedViewRenderer implements ViewRendererInterface
{
    private ViewRendererInterface $decoratedRenderer;

    private CacheInterface $cachePool;

    private ElementCacheKeyGeneratorInterface $elementCacheKeyProvider;

    private int $cacheTtl;

    /** @var class-string */
    private string $viewClass;

    /**
     * @param class-string $viewClass
     */
    public function __construct(
        ViewRendererInterface $decoratedRenderer,
        CacheInterface $cachePool,
        ElementCacheKeyGeneratorInterface $elementCacheKeyProvider,
        int $cacheTtl,
        string $viewClass
    ) {
        $this->decoratedRenderer = $decoratedRenderer;
        $this->cachePool = $cachePool;
        $this->elementCacheKeyProvider = $elementCacheKeyProvider;
        $this->cacheTtl = $cacheTtl;
        $this->viewClass = $viewClass;
    }

    public function render($view): string
    {
        $cacheKey = $this->elementCacheKeyProvider->generateCacheKey($view, $this->viewClass);

        /** @psalm-suppress ArgumentTypeCoercion */
        return $this->cachePool->get($cacheKey, function (ItemInterface $item) use ($view): string {
            $item->expiresAfter($this->cacheTtl);

            return $this->decoratedRenderer->render($view);
        });
    }
}
