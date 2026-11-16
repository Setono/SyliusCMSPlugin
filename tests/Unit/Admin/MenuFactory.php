<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Admin;

use Knp\Menu\Integration\Symfony\RoutingExtension;
use Knp\Menu\Matcher\Matcher;
use Knp\Menu\Matcher\Voter\RouteVoter;
use Knp\Menu\MenuFactory as KnpMenuFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * KnpMenu as the admin builds menus: items with a route get its path, written here as "/<route>?<parameters>"
 */
final class MenuFactory
{
    private function __construct()
    {
    }

    public static function create(): KnpMenuFactory
    {
        $factory = new KnpMenuFactory();
        $factory->addExtension(new RoutingExtension(new class() implements UrlGeneratorInterface {
            /**
             * @param array<mixed> $parameters
             */
            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
            {
                return rtrim('/' . $name . '?' . http_build_query($parameters), '?');
            }

            public function setContext(\Symfony\Component\Routing\RequestContext $context): void
            {
            }

            public function getContext(): \Symfony\Component\Routing\RequestContext
            {
                return new \Symfony\Component\Routing\RequestContext();
            }
        }));

        return $factory;
    }

    /**
     * What tells the admin's menus which item is current, on a page of this route
     *
     * @param array<string, string> $parameters
     */
    public static function matcherOn(string $route, array $parameters = []): Matcher
    {
        $requestStack = new RequestStack();
        $requestStack->push(new Request(attributes: ['_route' => $route, '_route_params' => $parameters]));

        return new Matcher([new RouteVoter($requestStack)]);
    }
}
