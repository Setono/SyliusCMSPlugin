<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preview;

use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The preview the current page shows, from the signed token in the main request's _cms_preview query parameter.
 * It's read from the main request, so slots rendered in sub-requests preview too, and an invalid or expired token
 * is ignored, so the request gets the shopper's response. See "Preview mode and share links" in docs/spec/rendering.md
 *
 * @internal
 */
final class PreviewContext
{
    public const QUERY_PARAMETER = '_cms_preview';

    /**
     * Each main request keeps the preview it started with, even if its token expires while it renders
     *
     * Wrapped in an array, because WeakMap::offsetExists() treats a null value as missing
     *
     * @var \WeakMap<Request, array{Preview|null}>
     */
    private \WeakMap $previews;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly PreviewTokenSigner $signer,
    ) {
        $this->previews = new \WeakMap();
    }

    public function getPreview(): ?Preview
    {
        $request = $this->requestStack->getMainRequest();
        if (null === $request) {
            return null;
        }

        if (!$this->previews->offsetExists($request)) {
            $token = $request->query->all()[self::QUERY_PARAMETER] ?? null;
            $this->previews[$request] = [is_string($token) ? $this->signer->verify($token) : null];
        }

        return $this->previews[$request][0];
    }

    /**
     * The preview mode: builder in the builder's preview, otherwise null. Share links add share
     *
     * @return RenderContext::PREVIEW_MODE_*|null
     */
    public function getMode(): ?string
    {
        return null === $this->getPreview() ? null : RenderContext::PREVIEW_MODE_BUILDER;
    }
}
