<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Tests\Support\FixtureKernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The test application plus a content type registered with #[AsResource], as a project registers one without
 * translatable fields of its own: Sylius reads the attribute in sylius_resource.mapping.paths, and Doctrine maps the
 * entity in a mapping of its own. See "Content types" in docs/spec/extending.md
 */
final class AsResourceContentTypeKernel extends FixtureKernel
{
    protected function cacheSuffix(): string
    {
        return 'as_resource_content_type';
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->loadFromExtension('sylius_resource', [
            'mapping' => ['paths' => [__DIR__ . '/AsResource']],
        ]);
        $container->loadFromExtension('doctrine', [
            'orm' => [
                'mappings' => [
                    'AsResourceContentType' => [
                        'is_bundle' => false,
                        'type' => 'attribute',
                        'dir' => __DIR__ . '/AsResource',
                        'prefix' => 'Setono\SyliusCMSPlugin\Tests\Functional\Fixture\AsResource',
                    ],
                ],
            ],
        ]);
    }
}
