<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Tests\Application\Kernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The test application plus the Greeting block type, whose template translates its text. It has a cache of its own,
 * so the block type stays out of every other test
 */
final class GreetingKernel extends Kernel
{
    public function getProjectDir(): string
    {
        return dirname(__DIR__, 2) . '/Application';
    }

    public function getCacheDir(): string
    {
        return parent::getCacheDir() . '_greeting';
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Registered the way an app's services.yaml registers its classes: autoconfigured, with no tag of its own
        $container->register(Greeting::class, Greeting::class)->setAutoconfigured(true);
    }
}
