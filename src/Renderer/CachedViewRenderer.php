<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Renderer;

use Setono\SyliusCMSPlugin\Generator\ElementCacheKeyGeneratorInterface;
use Setono\SyliusCMSPlugin\Model\ViewInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @implements RendererInterface<ViewInterface>
 */
final class CachedViewRenderer implements RendererInterface
{
    /** @var RendererInterface<ViewInterface> */
    private RendererInterface $decoratedRenderer;

    private CacheInterface $cachePool;

    private ElementCacheKeyGeneratorInterface $elementCacheKeyGenerator;

    private int $cacheTtl;

    /** @var class-string */
    private string $viewClass;

    /**
     * @param RendererInterface<ViewInterface> $decoratedRenderer
     * @param class-string $viewClass
     */
    public function __construct(
        RendererInterface $decoratedRenderer,
        CacheInterface $cachePool,
        ElementCacheKeyGeneratorInterface $elementCacheKeyGenerator,
        int $cacheTtl,
        string $viewClass
    ) {
        $this->decoratedRenderer = $decoratedRenderer;
        $this->cachePool = $cachePool;
        $this->elementCacheKeyGenerator = $elementCacheKeyGenerator;
        $this->cacheTtl = $cacheTtl;
        $this->viewClass = $viewClass;
    }

    public function render($element): Response
    {
        $cacheKey = $this->elementCacheKeyGenerator->generateCacheKey($element, $this->viewClass);

        /** @psalm-suppress ArgumentTypeCoercion */
        return $this->cachePool->get($cacheKey, function (ItemInterface $item) use ($element): Response {
            $item->expiresAfter($this->cacheTtl);

            return $this->decoratedRenderer->render($element);
        });
    }
}
