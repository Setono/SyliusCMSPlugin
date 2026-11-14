<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preview;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Tells the page it's in preview mode, and keeps preview responses out of caches and search engines.
 * See "Preview mode and share links" in docs/spec/rendering.md
 */
final readonly class PreviewModeListener
{
    /**
     * The main request's attribute that holds the preview mode: builder, share, or unset for shoppers. A public contract
     */
    public const REQUEST_ATTRIBUTE = '_cms_preview_mode';

    public function __construct(private PreviewContext $previewContext)
    {
    }

    /**
     * Runs before routing, so the route condition, controllers and listeners all see the attribute
     */
    public function onRequest(RequestEvent $event): void
    {
        $mode = $this->previewContext->getMode();
        if (!$event->isMainRequest() || null === $mode) {
            return;
        }

        $event->getRequest()->attributes->set(self::REQUEST_ATTRIBUTE, $mode);
    }

    /**
     * Runs after the session listener, which would otherwise add its own Cache-Control directives
     */
    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || null === $this->previewContext->getMode()) {
            return;
        }

        $headers = $event->getResponse()->headers;
        $headers->set('Cache-Control', 'no-store');
        $headers->set('X-Robots-Tag', 'noindex');
    }
}
