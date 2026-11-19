<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Tests\Support\FixtureKernel;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The test application with the caching cookbook's cache, docs/cookbook/caching.md, from its config file. The test
 * environment keeps cache.app in memory, where it goes with the kernel between requests, so the cookbook's pool is kept
 * on the filesystem here instead, as cache.app is by default
 */
final class CMSCacheKernel extends FixtureKernel
{
    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        parent::registerContainerConfiguration($loader);

        $loader->load($this->getProjectDir() . '/config/cookbook/caching.yaml');
        $loader->load(static function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', [
                'cache' => ['pools' => ['app.cms_cache' => ['adapter' => 'cache.adapter.filesystem']]],
            ]);
        });
    }

    protected function cacheSuffix(): string
    {
        return 'cms_cache';
    }
}
