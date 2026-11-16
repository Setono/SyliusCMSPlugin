<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Tests\Support\FixtureKernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The test application with the shipped stylesheet off and the Spacer block type disabled,
 * as a project sets them in its own config
 */
final class SwitchedOffKernel extends FixtureKernel
{
    protected function cacheSuffix(): string
    {
        return 'switched_off';
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->loadFromExtension('setono_sylius_cms', [
            'assets' => false,
            'disabled' => ['block_types' => ['spacer']],
        ]);
    }
}
