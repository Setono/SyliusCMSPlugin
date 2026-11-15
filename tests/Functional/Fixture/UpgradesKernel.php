<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Tests\Support\FixtureKernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The test application plus the Callout block type at version 2 and its upgrader from version 1
 */
final class UpgradesKernel extends FixtureKernel
{
    protected function cacheSuffix(): string
    {
        return 'upgrades';
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Registered the way an app's services.yaml registers its classes: autoconfigured, with no tag of its own
        $container->register(Callout::class, Callout::class)->setAutoconfigured(true);
        $container->register(CalloutFromOne::class, CalloutFromOne::class)->setAutoconfigured(true);
    }
}
