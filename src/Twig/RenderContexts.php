<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig;

use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Rendering\RenderContextFactoryInterface;

/**
 * The render context a cms_* function renders with: a CMS template's, or else the shop's, for a call from any other
 * template
 *
 * @internal
 */
final readonly class RenderContexts
{
    public function __construct(private RenderContextFactoryInterface $renderContextFactory)
    {
    }

    /**
     * @param array<string, mixed> $context the template's variables
     */
    public function of(array $context): RenderContext
    {
        $renderContext = $context[CMSRuntime::RENDER_CONTEXT] ?? null;

        return $renderContext instanceof RenderContext ? $renderContext : $this->renderContextFactory->create();
    }
}
