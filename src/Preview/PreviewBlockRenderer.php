<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preview;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Setono\SyliusCMSPlugin\Block\Snippet;
use Setono\SyliusCMSPlugin\Rendering\BlockRendererInterface;
use Setono\SyliusCMSPlugin\Rendering\BlockView;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Twig\Error\RuntimeError;

/**
 * In the builder's preview, brackets each block's output with HTML comment markers that carry its key and block type,
 * so the bridge can find, select and patch it. Block templates need no code of their own for this, and shoppers and
 * share links get no markers. See "The bridge" in docs/spec/builder.md
 *
 * A block that throws while rendering in the preview shows its error message in place of its output, in dev and in
 * production alike, and is logged. Each block renders into its own string, so the rest of the preview still renders.
 * See "Failures" in docs/spec/rendering.md
 *
 * A Snippet block is marked, but the snippet's blocks it shows aren't: they're another entry's, edited there.
 *
 * The marker format is internal: only the bridge reads it (assets/builder/src/bridge/markers.ts)
 */
final readonly class PreviewBlockRenderer implements BlockRendererInterface
{
    private const ERROR_STYLE = 'box-sizing:border-box;margin:0.5rem 0;padding:0.75rem 1rem;border:2px dashed #c92a2a;border-radius:4px;background:#fff5f5;color:#a51d1d;font:14px/1.4 system-ui,sans-serif;white-space:pre-wrap';

    public function __construct(
        private BlockRendererInterface $renderer,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function render(BlockView $view, RenderContext $context): string
    {
        if (RenderContext::PREVIEW_MODE_BUILDER !== $context->previewMode) {
            return $this->renderer->render($view, $context);
        }

        try {
            // What a Snippet block shows is another entry's blocks, which the builder neither selects nor types in, so
            // they render as shoppers see them, keeping Shown to and Date
            $html = $this->renderer->render($view, $view->block instanceof Snippet ? $context->withPreviewMode(null) : $context);
        } catch (\Throwable $e) {
            $html = $this->error($view, $e);
        }

        return sprintf('<!--cms:block %s %s-->%s<!--/cms:block %1$s-->', $view->key, $view->type, $html);
    }

    private function error(BlockView $view, \Throwable $exception): string
    {
        $message = self::message($exception);

        $this->logger->error('The block {key} of type {type} could not be rendered in the preview: {message}', [
            'key' => $view->key,
            'type' => $view->type,
            'message' => $message,
            'exception' => $exception,
        ]);

        return sprintf(
            '<div data-cms-block-error role="alert" style="%s">%s</div>',
            self::ERROR_STYLE,
            htmlspecialchars($message, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8'),
        );
    }

    /**
     * The message of what went wrong: Twig wraps an exception a template threw, so that's unwrapped
     */
    private static function message(\Throwable $exception): string
    {
        while ($exception instanceof RuntimeError && null !== $exception->getPrevious()) {
            $exception = $exception->getPrevious();
        }

        return $exception->getMessage();
    }
}
