<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media\Liip;

use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Lets CDNs keep the redirect that answers a variant's runtime URL for a year, while browsers ask again each time. The
 * URL names the file's storage path and is signed with the focal point, both of which a replace or a moved focal point
 * changes, so it leads to the same variant for as long as that exists. Variants removed by hand, such as with
 * liip:imagine:cache:remove, keep their URLs: a CDN can be purged then, but a visitor's browser can't, so browsers don't
 * keep it. Only the plugin's filter sets get it: Liip's redirect status is left
 * as Liip is configured, and Liip's other redirects, such as for Sylius's product images, which can be replaced at the
 * same path, keep what Liip sends. See "Image processing" in docs/spec/media.md
 *
 * @internal
 */
final class VariantRedirectCacheListener
{
    /** How long CDNs keep the redirect: a year, in seconds */
    public const SHARED_MAX_AGE = 31_536_000;

    /** The route of Liip's resolve step for a URL with runtime filters */
    private const ROUTE = 'liip_imagine_filter_runtime';

    public function __invoke(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();
        if (self::ROUTE !== $request->attributes->get('_route') ||
            !in_array($request->attributes->get('filter'), FilterSets::all(), true) ||
            !$response->isRedirection()) {
            return;
        }

        $response->setPublic();
        $response->setMaxAge(0);
        $response->setSharedMaxAge(self::SHARED_MAX_AGE);
    }
}
