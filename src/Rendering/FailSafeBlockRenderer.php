<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

/**
 * Keeps a block that throws while it renders for the shop or a share link from breaking the page: the failure handler
 * decides, and in production the block drops out. Each block renders into its own string, so no half-printed markup is
 * left behind, and its parent, the rest of the snippet and the page still show. The builder's preview shows the error
 * on the block instead (Preview\PreviewBlockRenderer). See "Failures" in docs/spec/rendering.md
 */
final readonly class FailSafeBlockRenderer implements BlockRendererInterface
{
    public function __construct(
        private BlockRendererInterface $renderer,
        private FailureHandlerInterface $failures,
    ) {
    }

    public function render(BlockView $view, RenderContext $context): string
    {
        if (RenderContext::PREVIEW_MODE_BUILDER === $context->previewMode) {
            return $this->renderer->render($view, $context);
        }

        try {
            return $this->renderer->render($view, $context);
        } catch (\Throwable $e) {
            $this->failures->blockFailed($view, $e);

            return '';
        }
    }
}
