<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Tests\Application\Kernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The test application plus the Broken block type, whose template always throws. It has a cache of its own,
 * so the block type stays out of every other test
 */
final class BrokenBlockKernel extends Kernel
{
    public function getProjectDir(): string
    {
        return dirname(__DIR__, 2) . '/Application';
    }

    public function getCacheDir(): string
    {
        return parent::getCacheDir() . '_broken_block';
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Registered the way an app's services.yaml registers its classes: autoconfigured, with no tag of its own
        $container->register(Broken::class, Broken::class)->setAutoconfigured(true);
    }
}
