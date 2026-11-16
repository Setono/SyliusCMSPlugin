<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Tests\Support\FixtureKernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The test application with an upload limit of 1 KB, below PHP's, as a project sets it in
 * setono_sylius_cms.media.max_upload_size
 */
final class SmallUploadLimitKernel extends FixtureKernel
{
    protected function cacheSuffix(): string
    {
        return 'small_upload_limit';
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->loadFromExtension('setono_sylius_cms', [
            'media' => ['max_upload_size' => '1K'],
        ]);
    }
}
