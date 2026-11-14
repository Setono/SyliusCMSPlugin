<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preview;

use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Twig\Environment;

/**
 * Injects the bridge script at the end of a page in the builder's preview, which shoppers and share links never get.
 * It works on any shop page, whatever its theme. See "The bridge" in docs/spec/builder.md
 */
final readonly class BridgeScriptListener
{
    public const TEMPLATE = '@SetonoSyliusCMSPlugin/preview/bridge.html.twig';

    public function __construct(
        private PreviewContext $previewContext,
        private Environment $twig,
    ) {
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || RenderContext::PREVIEW_MODE_BUILDER !== $this->previewContext->getMode()) {
            return;
        }

        $response = $event->getResponse();
        $contentType = $response->headers->get('Content-Type');
        if ($response->isRedirection() || (null !== $contentType && !str_contains(strtolower($contentType), 'html'))) {
            return;
        }

        // A streamed or binary response has no content to inject into
        $content = $response->getContent();
        if (false === $content) {
            return;
        }

        $position = strripos($content, '</body>');
        if (false === $position) {
            return;
        }

        $response->setContent(substr($content, 0, $position) . $this->twig->render(self::TEMPLATE) . substr($content, $position));
    }
}
