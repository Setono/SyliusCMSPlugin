<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Bestsellers\BestsellersCalculatorInterface;
use Setono\SyliusCMSPlugin\Tests\Support\FixtureKernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The test application with a project's bestsellers calculator, which the calculator interface is aliased to, as a
 * project with analytics data does
 */
final class AnalyticsBestsellersKernel extends FixtureKernel
{
    protected function cacheSuffix(): string
    {
        return 'analytics_bestsellers';
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->register(AnalyticsBestsellers::class, AnalyticsBestsellers::class);
        $container->setAlias(BestsellersCalculatorInterface::class, AnalyticsBestsellers::class);
    }
}
