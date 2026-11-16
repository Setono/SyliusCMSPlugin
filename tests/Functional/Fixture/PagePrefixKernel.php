<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Tests\Support\FixtureKernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The test application with pages under the prefix "pg", as a project sets it in setono_sylius_cms.content_types
 */
final class PagePrefixKernel extends FixtureKernel
{
    protected function cacheSuffix(): string
    {
        return 'page_prefix';
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->loadFromExtension('setono_sylius_cms', [
            'content_types' => ['page' => ['prefix' => 'pg']],
        ]);
    }
}
