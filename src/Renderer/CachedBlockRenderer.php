<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Renderer;

use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Setono\SyliusCMSPlugin\Provider\CmsElementCacheKeyProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class CachedBlockRenderer implements BlockRendererInterface
{
    private BlockRendererInterface $decoratedRenderer;

    private CacheInterface $cachePool;

    private CmsElementCacheKeyProviderInterface $cmsElementCacheKeyProvider;

    private int $cacheTtl;

    private string $blockClass;

    public function __construct(
        BlockRendererInterface $decoratedRenderer,
        CacheInterface $cachePool,
        CmsElementCacheKeyProviderInterface $cmsElementCacheKeyProvider,
        int $cacheTtl,
        string $blockClass
    ) {
        $this->decoratedRenderer = $decoratedRenderer;
        $this->cachePool = $cachePool;
        $this->cmsElementCacheKeyProvider = $cmsElementCacheKeyProvider;
        $this->cacheTtl = $cacheTtl;
        $this->blockClass = $blockClass;
    }

    public function render($block): string
    {
        $cacheKey = $this->cmsElementCacheKeyProvider->getCacheKey($block, $this->blockClass);

        return $this->cachePool->get($cacheKey, function (ItemInterface $item) use ($block): string {
            $item->expiresAfter($this->cacheTtl);

            return $this->decoratedRenderer->render($block);
        });
    }
}
