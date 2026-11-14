<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Tests\Application\Kernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The test application plus the Callout block type at version 2 and its upgrader from version 1. It has a cache of
 * its own, so the block type stays out of every other test
 */
final class UpgradesKernel extends Kernel
{
    public function getProjectDir(): string
    {
        return dirname(__DIR__, 2) . '/Application';
    }

    public function getCacheDir(): string
    {
        return parent::getCacheDir() . '_upgrades';
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Registered the way an app's services.yaml registers its classes: autoconfigured, with no tag of its own
        $container->register(Callout::class, Callout::class)->setAutoconfigured(true);
        $container->register(CalloutFromOne::class, CalloutFromOne::class)->setAutoconfigured(true);
    }
}
