<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Renderer;

use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Setono\SyliusCMSPlugin\Provider\CmsElementCacheKeyProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class CachedViewRenderer implements ViewRendererInterface
{
    private ViewRendererInterface $decoratedRenderer;

    private CacheInterface $cachePool;

    private CmsElementCacheKeyProviderInterface $cmsElementCacheKeyProvider;

    private int $cacheTtl;

    private string $viewClass;

    public function __construct(
        ViewRendererInterface $decoratedRenderer,
        CacheInterface $cachePool,
        CmsElementCacheKeyProviderInterface $cmsElementCacheKeyProvider,
        int $cacheTtl,
        string $viewClass
    ) {
        $this->decoratedRenderer = $decoratedRenderer;
        $this->cachePool = $cachePool;
        $this->cmsElementCacheKeyProvider = $cmsElementCacheKeyProvider;
        $this->cacheTtl = $cacheTtl;
        $this->viewClass = $viewClass;
    }

    public function render($view): string
    {
        $cacheKey = $this->cmsElementCacheKeyProvider->getCacheKey($view, $this->viewClass);

        return $this->cachePool->get($cacheKey, function (ItemInterface $item) use ($view): string {
            $item->expiresAfter($this->cacheTtl);

            return $this->decoratedRenderer->render($view);
        });
    }
}
