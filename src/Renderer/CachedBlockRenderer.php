<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Renderer;

use Setono\SyliusCMSPlugin\Generator\ElementCacheKeyGeneratorInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class CachedBlockRenderer implements BlockRendererInterface
{
    private BlockRendererInterface $decoratedRenderer;

    private CacheInterface $cachePool;

    private ElementCacheKeyGeneratorInterface $elementCacheKeyProvider;

    private int $cacheTtl;

    private string $blockClass;

    public function __construct(
        BlockRendererInterface $decoratedRenderer,
        CacheInterface $cachePool,
        ElementCacheKeyGeneratorInterface $elementCacheKeyProvider,
        int $cacheTtl,
        string $blockClass
    ) {
        $this->decoratedRenderer = $decoratedRenderer;
        $this->cachePool = $cachePool;
        $this->elementCacheKeyProvider = $elementCacheKeyProvider;
        $this->cacheTtl = $cacheTtl;
        $this->blockClass = $blockClass;
    }

    public function render($block): string
    {
        $cacheKey = $this->elementCacheKeyProvider->getCacheKey($block, $this->blockClass);

        /** @psalm-suppress ArgumentTypeCoercion */
        return $this->cachePool->get($cacheKey, function (ItemInterface $item) use ($block): string {
            $item->expiresAfter($this->cacheTtl);

            return $this->decoratedRenderer->render($block);
        });
    }
}
