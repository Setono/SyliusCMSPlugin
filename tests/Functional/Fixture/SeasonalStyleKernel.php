<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Tests\Application\Kernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The test application with one more block style in its config, a seasonal look for the Group,
 * as a project adds one for a while. It has its own cache, so it's built once and reused
 */
final class SeasonalStyleKernel extends Kernel
{
    public function getProjectDir(): string
    {
        return dirname(__DIR__, 2) . '/Application';
    }

    public function getCacheDir(): string
    {
        return parent::getCacheDir() . '_seasonal_style';
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->loadFromExtension('setono_sylius_cms', [
            'block_styles' => ['group' => ['seasonal' => ['label' => 'app.cms.block_style.seasonal', 'class' => 'app-group--seasonal']]],
        ]);
    }
}
