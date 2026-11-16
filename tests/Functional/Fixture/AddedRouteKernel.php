<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Tests\Support\FixtureKernel;
use Symfony\Bundle\FrameworkBundle\Controller\RedirectController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * The test application with a route of its own at /{_locale}/stores, as an app adds one after entries were published
 */
final class AddedRouteKernel extends FixtureKernel
{
    protected function cacheSuffix(): string
    {
        return 'added_route';
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        parent::configureRoutes($routes);

        $routes->add('app_store_locator', '/{_locale}/stores')
            ->controller(RedirectController::class)
            ->defaults(['route' => 'sylius_shop_homepage'])
            ->requirements(['_locale' => '^[A-Za-z]{2,4}(_([A-Za-z]{4}|[0-9]{3}))?(_([A-Za-z]{2}|[0-9]{3}))?$'])
            ->methods(['GET'])
        ;
    }
}
