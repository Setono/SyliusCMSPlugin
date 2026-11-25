<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Tests\Support\FixtureKernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The test application plus the Signed in condition kind, as a project adds one and a later deploy may remove it
 */
final class SignedInConditionKernel extends FixtureKernel
{
    protected function cacheSuffix(): string
    {
        return 'signed_in_condition';
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Registered the way an app's services.yaml registers its classes: autoconfigured, with no tag of its own
        $container->register(SignedIn::class, SignedIn::class)->setAutoconfigured(true);
    }
}
