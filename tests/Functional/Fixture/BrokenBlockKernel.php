<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Tests\Support\FixtureKernel;
use Setono\SyliusCMSPlugin\Tests\Support\RecordingLogger;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The test application plus the Broken block type, whose template always throws, and a logger that keeps what's
 * logged (RecordingLogger)
 */
final class BrokenBlockKernel extends FixtureKernel
{
    protected function cacheSuffix(): string
    {
        return 'broken_block';
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Registered the way an app's services.yaml registers its classes: autoconfigured, with no tag of its own
        $container->register(Broken::class, Broken::class)->setAutoconfigured(true);

        $container->register(RecordingLogger::class, RecordingLogger::class)
            ->setDecoratedService('logger')
            ->setArguments([new Reference('.inner')])
            ->setPublic(true)
        ;
    }
}
