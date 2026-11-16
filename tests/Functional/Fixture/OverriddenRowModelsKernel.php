<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\Entity\EntryAudienceGroup;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\Entity\EntryChannel;
use Setono\SyliusCMSPlugin\Tests\Support\FixtureKernel;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The test application with the entry_channel and entry_audience_group models overridden by subclasses, as a project
 * overrides a shipped model in its own config and maps the subclass. The tables stay the same
 */
final class OverriddenRowModelsKernel extends FixtureKernel
{
    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        parent::registerContainerConfiguration($loader);

        // DAMA keeps Doctrine's metadata and query caches in static arrays every kernel in the process shares, where the
        // test application's kernel caches the shipped models as entities. This kernel maps them differently, so it
        // keeps its own. Loaded after the test application's config, which turns them on
        $loader->load(static function (ContainerBuilder $container): void {
            $container->loadFromExtension('dama_doctrine_test', [
                'enable_static_meta_data_cache' => false,
                'enable_static_query_cache' => false,
            ]);
        });
    }

    protected function cacheSuffix(): string
    {
        return 'overridden_row_models';
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->loadFromExtension('setono_sylius_cms', [
            'resources' => [
                'entry_channel' => ['classes' => ['model' => EntryChannel::class]],
                'entry_audience_group' => ['classes' => ['model' => EntryAudienceGroup::class]],
            ],
        ]);

        $container->loadFromExtension('doctrine', [
            'orm' => [
                'mappings' => [
                    'OverriddenRowModels' => [
                        'is_bundle' => false,
                        'type' => 'attribute',
                        'dir' => __DIR__ . '/Entity',
                        'prefix' => 'Setono\SyliusCMSPlugin\Tests\Functional\Fixture\Entity',
                    ],
                ],
            ],
        ]);
    }
}
