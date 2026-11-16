<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Tests\Support\FixtureKernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The test application plus a second Page layout that is marked as Page's default too, which fails the build
 */
final class TwoDefaultLayoutsKernel extends FixtureKernel
{
    protected function cacheSuffix(): string
    {
        return 'two_default_layouts';
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->register(AlsoDefault::class, AlsoDefault::class)->setAutoconfigured(true);
    }
}
