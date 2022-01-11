<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Renderer;

use Setono\SyliusCMSPlugin\Generator\ElementCacheKeyGeneratorInterface;
use Setono\SyliusCMSPlugin\Model\BlockInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @implements RendererInterface<BlockInterface>
 */
final class CachedBlockRenderer implements RendererInterface
{
    /** @var RendererInterface<BlockInterface> */
    private RendererInterface $decoratedRenderer;

    private CacheInterface $cachePool;

    private ElementCacheKeyGeneratorInterface $elementCacheKeyGenerator;

    private int $cacheTtl;

    /** @var class-string */
    private string $blockClass;

    /**
     * @param RendererInterface<BlockInterface> $decoratedRenderer
     * @param class-string $blockClass
     */
    public function __construct(
        RendererInterface $decoratedRenderer,
        CacheInterface $cachePool,
        ElementCacheKeyGeneratorInterface $elementCacheKeyGenerator,
        int $cacheTtl,
        string $blockClass
    ) {
        $this->decoratedRenderer = $decoratedRenderer;
        $this->cachePool = $cachePool;
        $this->elementCacheKeyGenerator = $elementCacheKeyGenerator;
        $this->cacheTtl = $cacheTtl;
        $this->blockClass = $blockClass;
    }

    public function render($element): Response
    {
        $cacheKey = $this->elementCacheKeyGenerator->generateCacheKey($element, $this->blockClass);

        /** @psalm-suppress ArgumentTypeCoercion */
        return $this->cachePool->get($cacheKey, function (ItemInterface $item) use ($element): Response {
            $item->expiresAfter($this->cacheTtl);

            return $this->decoratedRenderer->render($element);
        });
    }
}
