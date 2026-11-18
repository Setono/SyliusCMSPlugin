<?php

declare(strict_types=1);

use Setono\SyliusCMSPlugin\Controller\Shop\ShowEntryAction;
use Setono\SyliusCMSPlugin\Controller\Shop\SnippetPreviewAction;
use Setono\SyliusCMSPlugin\Routing\RouteChecker;
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

// The plain shop page the builder previews a snippet on, which answers 404 to anyone but the builder's preview of one
$routes->add(SnippetPreviewAction::ROUTE, new Route(
    path: '/_cms/snippet-preview',
    defaults: ['_controller' => SnippetPreviewAction::class],
    methods: ['GET'],
));

$routes->add(RouteChecker::ENTRY_ROUTE, new Route(
    path: '/{path}',
    defaults: ['_controller' => ShowEntryAction::class],
    requirements: ['path' => '.+(?<!/)'],
    methods: ['GET'],
    // Looks the URL up by the channel, the locale from the URL and the path. A URL no entry claims fails the condition,
    // so the request falls through to Sylius's ordinary 404. Routing\RouteChecker's requests, which ask whether
    // another route takes a URL, don't look it up
    condition: sprintf("not request.attributes.get('%s') and service('setono_sylius_cms_entry_url_matcher').matches(params)", RouteChecker::REQUEST_ATTRIBUTE),
), -1000);

return $routes;
