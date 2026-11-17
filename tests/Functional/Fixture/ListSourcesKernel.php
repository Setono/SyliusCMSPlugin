<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Query\VisibleProductsQueryInterface;
use Setono\SyliusCMSPlugin\Tests\Support\FixtureKernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The test application with the Newest source switched off, plus a project's Featured source and the Unread products
 * block type, whose template never reads its list
 */
final class ListSourcesKernel extends FixtureKernel
{
    protected function cacheSuffix(): string
    {
        return 'list_sources';
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->loadFromExtension('setono_sylius_cms', ['disabled' => ['sources' => ['newest_products']]]);

        // Registered the way an app's services.yaml registers its classes: autoconfigured, with no tag of its own
        $container->register(FeaturedProducts::class, FeaturedProducts::class)
            ->setArguments([new Reference(VisibleProductsQueryInterface::class)])
            ->setAutoconfigured(true)
        ;
        $container->register(UnreadProducts::class, UnreadProducts::class)->setAutoconfigured(true);
    }
}
