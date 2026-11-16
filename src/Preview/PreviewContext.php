<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preview;

use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Repository\ShareLinkRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The preview the current page shows, from the signed token in the main request's _cms_preview query parameter, or
 * from the share link whose token is in its _cms_share query parameter. It's read from the main request, so slots
 * rendered in sub-requests preview too, and an invalid or expired token, or a revoked share link's, is ignored, so the
 * request gets the shopper's response. See "Preview mode and share links" in docs/spec/rendering.md
 *
 * @internal
 */
final class PreviewContext
{
    public const QUERY_PARAMETER = '_cms_preview';

    /** The query parameter of a share link's token */
    public const SHARE_QUERY_PARAMETER = '_cms_share';

    /**
     * Each main request keeps the preview it started with, even if its token expires while it renders
     *
     * Wrapped in an array, because WeakMap::offsetExists() treats a null value as missing
     *
     * @var \WeakMap<Request, array{Preview|null}>
     */
    private \WeakMap $previews;

    /**
     * @param (\Closure(): ShareLinkRepositoryInterface)|null $shareLinks where share links are looked up by their token,
     *                                                                   or null to read only the builder's preview
     *                                                                   tokens. A closure, because Sylius's channel and
     *                                                                   locale contexts read the preview, and Doctrine's
     *                                                                   listeners read them while the entity manager the
     *                                                                   repository needs is being made
     */
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly PreviewTokenSigner $signer,
        private readonly ?\Closure $shareLinks = null,
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
            $this->previews[$request] = [$this->read($request)];
        }

        return $this->previews[$request][0];
    }

    /**
     * The preview mode: builder in the builder's preview, share on a share link, otherwise null
     *
     * @return RenderContext::PREVIEW_MODE_*|null
     */
    public function getMode(): ?string
    {
        return $this->getPreview()?->getMode();
    }

    private function read(Request $request): ?Preview
    {
        $query = $request->query->all();

        $token = $query[self::QUERY_PARAMETER] ?? null;
        $preview = is_string($token) ? $this->signer->verify($token) : null;
        if (null !== $preview) {
            return $preview;
        }

        $shareToken = $query[self::SHARE_QUERY_PARAMETER] ?? null;
        if (null === $this->shareLinks || !is_string($shareToken) || '' === $shareToken) {
            return null;
        }

        $shareLink = ($this->shareLinks)()->lookUpToken($shareToken);
        if (null === $shareLink) {
            return null;
        }

        return new Preview($shareLink['entry'], $shareLink['channel'], $shareLink['locale'], null, $shareLink['shownTo'], $shareLink['date'], true);
    }
}
