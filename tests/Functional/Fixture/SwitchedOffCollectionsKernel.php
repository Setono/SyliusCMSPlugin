<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Tests\Support\FixtureKernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The test application with both shipped collections switched off as a whole in
 * setono_sylius_cms.disabled.content_types, the Blog and the FAQ, each with its categories, as a project that has
 * neither does
 */
final class SwitchedOffCollectionsKernel extends FixtureKernel
{
    protected function cacheSuffix(): string
    {
        return 'switched_off_collections';
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->loadFromExtension('setono_sylius_cms', [
            'disabled' => ['content_types' => ['blog_post', 'blog_category', 'faq_item', 'faq_category']],
        ]);
    }
}
