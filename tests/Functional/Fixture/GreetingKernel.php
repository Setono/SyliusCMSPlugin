<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Tests\Support\FixtureKernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The test application plus the Greeting block type, whose template translates its text, and the Signed in condition
 * kind
 */
final class GreetingKernel extends FixtureKernel
{
    protected function cacheSuffix(): string
    {
        return 'greeting';
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Registered the way an app's services.yaml registers its classes: autoconfigured, with no tag of its own
        $container->register(Greeting::class, Greeting::class)->setAutoconfigured(true);
        $container->register(SignedIn::class, SignedIn::class)->setAutoconfigured(true);
    }
}
