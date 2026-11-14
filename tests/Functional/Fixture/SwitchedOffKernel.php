<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Tests\Application\Kernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The test application with the shipped stylesheet off and the Spacer block type disabled,
 * as a project sets them in its own config. It has its own cache, so it's built once and reused
 */
final class SwitchedOffKernel extends Kernel
{
    public function getProjectDir(): string
    {
        return dirname(__DIR__, 2) . '/Application';
    }

    public function getCacheDir(): string
    {
        return parent::getCacheDir() . '_switched_off';
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
