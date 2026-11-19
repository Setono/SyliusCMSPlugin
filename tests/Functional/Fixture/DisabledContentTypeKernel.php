<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Tests\Support\FixtureKernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The test application with its store locations switched off in setono_sylius_cms.disabled.content_types, as a project
 * switches off a shipped collection's content type, such as blog_post
 */
final class DisabledContentTypeKernel extends FixtureKernel
{
    protected function cacheSuffix(): string
    {
        return 'disabled_content_type';
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->loadFromExtension('setono_sylius_cms', [
            'disabled' => ['content_types' => ['store_location']],
        ]);
    }
}
