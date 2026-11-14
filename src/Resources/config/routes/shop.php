<?php

declare(strict_types=1);

use Setono\SyliusCMSPlugin\Controller\Shop\ShowEntryAction;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/*
 * Import it under the shop's locale prefix (docs/spec/extending.md#routes):
 *
 *     setono_sylius_cms_shop:
 *         resource: '@SetonoSyliusCMSPlugin/Resources/config/routes/shop.php'
 *         prefix: /{_locale}
 *         requirements:
 *             _locale: ^[A-Za-z]{2,4}(_([A-Za-z]{4}|[0-9]{3}))?(_([A-Za-z]{2}|[0-9]{3}))?$
 *
 * The file returns a route collection instead of configuring routes, because only a collection can set
 * the priority of -1000 that makes this route match after every other route, whatever order the files load in (ADR 0015)
 */
$routes = new RouteCollection();

$routes->add('setono_sylius_cms_shop_entry', new Route(
    path: '/{path}',
    defaults: ['_controller' => ShowEntryAction::class],
    requirements: ['path' => '.+(?<!/)'],
    methods: ['GET'],
    // Looks the URL up by the channel, the locale from the URL and the path. A URL no entry claims fails the condition,
    // so the request falls through to Sylius's ordinary 404
    condition: "service('setono_sylius_cms_entry_url_matcher').matches(params)",
), -1000);

return $routes;
