<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Tests\Support\FixtureKernel;
use Setono\SyliusCMSPlugin\Tests\Support\RecordingLogger;
use Setono\SyliusCMSPlugin\Tests\Unit\Condition\Fixture\InStock;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The test application plus the Broken block type, whose template always throws, the Unbuildable one, which throws
 * when it's hydrated, the page-scope condition kind InStock, which always throws, a template on the login page that
 * renders a slot no one declares with cms_slot() (cms/slot/misspelled.html.twig), and a logger that keeps what's logged
 * (RecordingLogger)
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
        $container->register(Unbuildable::class, Unbuildable::class)->setAutoconfigured(true);
        $container->register(InStock::class, InStock::class)->setAutoconfigured(true);

        $container->loadFromExtension('sylius_ui', ['events' => [
            'sylius.shop.login.after_content_header' => ['blocks' => [
                'app_misspelled_slot' => ['template' => 'cms/slot/misspelled.html.twig'],
            ]],
        ]]);

        $container->register(RecordingLogger::class, RecordingLogger::class)
            ->setDecoratedService('logger')
            ->setArguments([new Reference('.inner')])
            ->setPublic(true)
        ;
    }
}
