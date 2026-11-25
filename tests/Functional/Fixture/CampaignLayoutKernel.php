<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Tests\Support\FixtureKernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The test application plus the Campaign layout for pages, whose headline is required
 */
final class CampaignLayoutKernel extends FixtureKernel
{
    protected function cacheSuffix(): string
    {
        return 'campaign_layout';
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Registered the way an app's services.yaml registers its classes: autoconfigured, with no tag of its own
        $container->register(Campaign::class, Campaign::class)->setAutoconfigured(true);
    }
}
