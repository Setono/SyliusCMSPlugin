<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Tests\Support\FixtureKernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The test application with a JSON manifest for Symfony's default asset package, as a project whose build versions its
 * files has: versioned-assets.json maps cms/opening-hours.js, the script of the opening hours field control, to the
 * name of its built file
 */
final class VersionedAssetsKernel extends FixtureKernel
{
    protected function cacheSuffix(): string
    {
        return 'versioned_assets';
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->loadFromExtension('framework', ['assets' => ['json_manifest_path' => __DIR__ . '/versioned-assets.json']]);
    }
}
