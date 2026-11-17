<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Tests\Support\FixtureKernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The test application plus the Media names block type, with a Media and a Media list field
 */
final class MediaFieldsKernel extends FixtureKernel
{
    protected function cacheSuffix(): string
    {
        return 'media_fields';
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Registered the way an app's services.yaml registers its classes: autoconfigured, with no tag of its own
        $container->register(MediaNames::class, MediaNames::class)->setAutoconfigured(true);
    }
}
