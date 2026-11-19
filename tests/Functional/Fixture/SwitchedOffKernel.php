<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Tests\Support\FixtureKernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The test application with the shipped stylesheet off, the Spacer block type disabled and the stock slot shop.footer
 * switched off, as a project sets them in its own config
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

        // The stock slot shop.footer's block on its template event, by the name the plugin gives it
        $container->loadFromExtension('sylius_ui', ['events' => [
            'sylius.shop.layout.footer' => ['blocks' => ['setono_sylius_cms.slot.shop.footer' => ['enabled' => false]]],
        ]]);
    }
}
