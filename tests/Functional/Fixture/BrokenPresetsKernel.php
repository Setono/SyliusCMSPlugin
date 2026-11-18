<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Tests\Support\FixtureKernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The test application with a second directory of code presets, as a bundle prepends its own, whose files have blocks
 * of a removed type, a block newer than the code's, blocks that don't fit, and one that isn't in the format. It switches
 * off the test application's product_spotlight preset
 */
final class BrokenPresetsKernel extends FixtureKernel
{
    protected function cacheSuffix(): string
    {
        return 'broken_presets';
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->loadFromExtension('setono_sylius_cms', [
            'presets' => ['paths' => [__DIR__ . '/presets/broken']],
            'disabled' => ['presets' => ['product_spotlight']],
        ]);
    }
}
