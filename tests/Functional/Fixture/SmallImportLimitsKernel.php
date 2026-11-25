<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Tests\Support\FixtureKernel;
use Setono\SyliusCMSPlugin\Transfer\Import\ExportSources;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The test application with an import taking media files of up to 1 KB and zips unpacking to up to 16 KB, as a project
 * sets them in setono_sylius_cms.transfer.max_file_size and max_unpacked_size, and JSON files of up to 4 KB, lowered on
 * the service, as that cap has no config key. The committed export unpacks to 9583 bytes, and its biggest JSON file is
 * 2980 bytes
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

        $container->addCompilerPass(new class() implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                $container->getDefinition(ExportSources::class)->replaceArgument(1, 4 * 1024);
            }
        });
    }
}
