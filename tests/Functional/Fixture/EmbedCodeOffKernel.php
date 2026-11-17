<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Tests\Support\FixtureKernel;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The test application with setono_sylius_cms.embed_code off, as it is in a project that hasn't opted in. The test
 * application's own config turns it on, and config loaded in build() comes before that, so it's switched off again
 * after the application's config is loaded
 */
final class EmbedCodeOffKernel extends FixtureKernel
{
    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        parent::registerContainerConfiguration($loader);

        $loader->load(static function (ContainerBuilder $container): void {
            $container->loadFromExtension('setono_sylius_cms', ['embed_code' => false]);
        });
    }

    protected function cacheSuffix(): string
    {
        return 'embed_code_off';
    }
}
