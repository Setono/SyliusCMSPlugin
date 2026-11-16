<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Admin;

use Knp\Menu\Integration\Symfony\RoutingExtension;
use Knp\Menu\Matcher\Matcher;
use Knp\Menu\Matcher\Voter\RouteVoter;
use Knp\Menu\MenuFactory as KnpMenuFactory;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Loader\PhpFileLoader;
use Symfony\Component\Routing\RequestContext;

/**
 * KnpMenu as the admin builds menus: items with a route get its path from the plugin's admin routes, imported under
 * /admin as the test application imports them, so an item can only point at a route the plugin has
 */
final class MenuFactory
{
    private function __construct()
    {
    }

    public static function create(): KnpMenuFactory
    {
        $routes = (new PhpFileLoader(new FileLocator()))->load(dirname(__DIR__, 3) . '/src/Resources/config/routes/admin.php');
        $routes->addPrefix('/admin');

        $factory = new KnpMenuFactory();
        $factory->addExtension(new RoutingExtension(new UrlGenerator($routes, new RequestContext())));

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
