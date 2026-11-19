<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Tests\Support\FixtureKernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The test application with a directory of code presets that are starting points, as a bundle prepends its own: Page's
 * default, Campaign page, on the landing layout with a tagline, and Countdown page, which has a block of a type the code
 * no longer has
 */
final class StartingPointsKernel extends FixtureKernel
{
    protected function cacheSuffix(): string
    {
        return 'starting_points';
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->loadFromExtension('setono_sylius_cms', [
            'presets' => ['paths' => [__DIR__ . '/presets/starting_points']],
            // So a starting point's Spacer is left out, as inserting leaves out blocks of a disabled type
            'disabled' => ['block_types' => ['spacer']],
        ]);
    }
}
