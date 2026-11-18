<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\CustomPage\CustomPage;
use Setono\SyliusCMSPlugin\Tests\Support\FixtureKernel;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The test application with the page model overridden by a subclass of Page, in the plugin's resources config, as a
 * project extends a shipped content type (docs/spec/extending.md#content-types). The subclass adds no columns, so the
 * schema stays the same
 */
final class CustomPageKernel extends FixtureKernel
{
    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        parent::registerContainerConfiguration($loader);

        // DAMA keeps Doctrine's metadata and query caches in static arrays every kernel in the process shares, where the
        // test application's kernel caches Page as the page entity. This kernel maps it differently, so it keeps its own
        $loader->load(static function (ContainerBuilder $container): void {
            $container->loadFromExtension('dama_doctrine_test', [
                'enable_static_meta_data_cache' => false,
                'enable_static_query_cache' => false,
            ]);
        });
    }

    protected function cacheSuffix(): string
    {
        return 'custom_page';
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->loadFromExtension('setono_sylius_cms', [
            'resources' => [
                'page' => ['classes' => ['model' => CustomPage::class]],
            ],
        ]);

        $container->loadFromExtension('doctrine', [
            'orm' => [
                'mappings' => [
                    'CustomPage' => [
                        'is_bundle' => false,
                        'type' => 'attribute',
                        'dir' => __DIR__ . '/CustomPage',
                        'prefix' => 'Setono\SyliusCMSPlugin\Tests\Functional\Fixture\CustomPage',
                    ],
                ],
            ],
        ]);
    }
}
