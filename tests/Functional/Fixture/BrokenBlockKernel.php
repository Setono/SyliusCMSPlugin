<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Tests\Support\FixtureKernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The test application plus the Broken block type, whose template always throws
 */
final class BrokenBlockKernel extends FixtureKernel
{
    protected function cacheSuffix(): string
    {
        return 'broken_block';
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Registered the way an app's services.yaml registers its classes: autoconfigured, with no tag of its own
        $container->register(Broken::class, Broken::class)->setAutoconfigured(true);
    }
}
