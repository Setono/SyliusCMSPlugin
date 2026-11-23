<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Tests\Support\FixtureKernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The test application with an import taking media files of up to 1 KB and zips unpacking to up to 16 KB, as a project
 * sets them in setono_sylius_cms.transfer.max_file_size and max_unpacked_size. The committed export unpacks to 9583
 * bytes
 */
final class SmallImportLimitsKernel extends FixtureKernel
{
    protected function cacheSuffix(): string
    {
        return 'small_import_limits';
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->loadFromExtension('setono_sylius_cms', [
            'transfer' => ['max_file_size' => '1K', 'max_unpacked_size' => '16K'],
        ]);
    }
}
