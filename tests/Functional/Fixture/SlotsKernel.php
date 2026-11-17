<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Tests\Support\FixtureKernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The test application plus slots of a project's own, as a project declares them: app.store_locator.top, which the
 * project's template cms/slot/store_locator.html.twig renders with cms_slot() below every page's content, and
 * app.announcements, tied to the shop layout's header template event. It also caps the stock slots shop.top and
 * store_homepage at 1, and adds the Promo layout for pages, whose template renders its own slot,
 * app.promo.after_blocks, with the entry being shown
 */
final class SlotsKernel extends FixtureKernel
{
    protected function cacheSuffix(): string
    {
        return 'slots';
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->loadFromExtension('setono_sylius_cms', ['slots' => [
            'app.store_locator.top' => ['label' => 'app.cms.slot.store_locator_top'],
            'app.announcements' => ['label' => 'app.cms.slot.announcements', 'template_event' => ['name' => 'sylius.shop.layout.header', 'priority' => 40]],
            'shop.top' => ['cap' => 1],
            'store_homepage' => ['cap' => 1],
            'app.promo.after_blocks' => ['label' => 'app.cms.slot.promo_after_blocks', 'provides' => ['entry']],
        ]]);

        // Registered the way an app's services.yaml registers its classes: autoconfigured, with no tag of its own
        $container->register(Promo::class, Promo::class)->setAutoconfigured(true);

        $container->loadFromExtension('sylius_ui', ['events' => [
            'sylius.shop.layout.after_content' => ['blocks' => [
                'app_store_locator' => ['template' => 'cms/slot/store_locator.html.twig', 'priority' => -100],
            ]],
        ]]);
    }
}
