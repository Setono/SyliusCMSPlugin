<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Tests\Support\FixtureKernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The test application with its own Landing layout disabled, as a project disables a layout in its own config
 */
final class DisabledLayoutKernel extends FixtureKernel
{
    protected function cacheSuffix(): string
    {
        return 'disabled_layout';
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->loadFromExtension('setono_sylius_cms', [
            'disabled' => ['layouts' => ['page.landing']],
        ]);
    }
}
