<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Routing;

use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ExceptionInterface;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Runs the URL through the app's own router, as a GET on the channel's hostname: the URL is generated from the entry
 * route, so it has whatever prefix the app imports the route under, and then matched. The entry route's condition
 * leaves the request alone (REQUEST_ATTRIBUTE), so checking a URL never looks it up: every route that matches is
 * another one. The router's context is put back afterwards
 */
final readonly class RouteChecker implements RouteCheckerInterface
{
    public const ENTRY_ROUTE = 'setono_sylius_cms_shop_entry';

    /** Set on the request the check matches, which the entry route's condition then turns down */
    public const REQUEST_ATTRIBUTE = '_setono_sylius_cms_route_check';

    public function __construct(private RouterInterface&RequestMatcherInterface $router)
    {
    }

    public function takenBy(?ChannelInterface $channel, string $localeCode, string $path): ?string
    {
        $original = $this->router->getContext();

        $context = clone $original;
        $context->setMethod('GET');
        $context->setBaseUrl('');
        $context->setHost($channel?->getHostname() ?? $original->getHost());
        $this->router->setContext($context);

        try {
            $url = $this->router->generate(self::ENTRY_ROUTE, ['_locale' => $localeCode, 'path' => $path]);

            $request = Request::create(sprintf('%s://%s%s', $context->getScheme(), $context->getHost(), $url));
            $request->attributes->set(self::REQUEST_ATTRIBUTE, true);

            $route = $this->router->matchRequest($request)['_route'] ?? null;
        } catch (ExceptionInterface) {
            // No route matches, only for another method, or the entry route can't make a URL of the path
            return null;
        } finally {
            $this->router->setContext($original);
        }

        return is_string($route) && self::ENTRY_ROUTE !== $route ? $route : null;
    }
}
