<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Rendering\EntryUrlLookupInterface;
use Setono\SyliusCMSPlugin\Rendering\PlacementProviderInterface;
use Setono\SyliusCMSPlugin\Tests\Application\Cache\CachedEntryUrlLookup;
use Setono\SyliusCMSPlugin\Tests\Application\Cache\CachedPlacementProvider;
use Setono\SyliusCMSPlugin\Tests\Application\Cache\CMSCacheInvalidator;
use Setono\SyliusCMSPlugin\Tests\Support\FixtureKernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The test application with the caching cookbook's cache, docs/cookbook/caching.md: its tag-aware pool and its three
 * services, wired as the cookbook wires them in a project's config. The test environment keeps cache.app in memory, where
 * it goes with the kernel between requests, so the pool is kept on the filesystem here instead, as cache.app is by
 * default
 */
final class CMSCacheKernel extends FixtureKernel
{
    /** The cookbook's pool */
    public const POOL = 'app.cms_cache';

    protected function cacheSuffix(): string
    {
        return 'cms_cache';
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->loadFromExtension('framework', [
            'cache' => ['pools' => [self::POOL => ['adapter' => 'cache.adapter.filesystem', 'tags' => true]]],
        ]);

        $container->register(CachedPlacementProvider::class, CachedPlacementProvider::class)
            ->setDecoratedService(PlacementProviderInterface::class)
            ->setArguments([new Reference(CachedPlacementProvider::class . '.inner'), new Reference(self::POOL)])
        ;

        $container->register(CachedEntryUrlLookup::class, CachedEntryUrlLookup::class)
            ->setDecoratedService(EntryUrlLookupInterface::class)
            ->setArguments([new Reference(CachedEntryUrlLookup::class . '.inner'), new Reference(self::POOL)])
        ;

        $container->register(CMSCacheInvalidator::class, CMSCacheInvalidator::class)
            ->setArguments([new Reference(self::POOL)])
            ->addTag('kernel.event_subscriber')
        ;
    }
}
