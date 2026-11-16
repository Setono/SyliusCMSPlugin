<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

/**
 * Gives the Sylius admin menu (sylius.menu.admin.main) one item, Content, which opens the hub. It sits before
 * Configuration, and is current on every page of the hub. See "Navigation" in docs/spec/admin.md
 */
final class MainMenuListener
{
    public const SECTION = 'setono_sylius_cms';

    public function __invoke(MenuBuilderEvent $event): void
    {
        $menu = $event->getMenu();

        $menu
            ->addChild(self::SECTION)
            ->setLabel('setono_sylius_cms.menu.admin.main.content.header')
            ->addChild('content', [
                'route' => 'setono_sylius_cms_admin_overview',
                'extras' => ['routes' => [['pattern' => '/^setono_sylius_cms_admin_/']]],
            ])
            ->setLabel('setono_sylius_cms.menu.admin.main.content.content')
            ->setLabelAttribute('icon', 'pencil alternate')
        ;

        $others = array_diff(array_keys($menu->getChildren()), [self::SECTION]);
        $configuration = array_search('configuration', $others, true);
        if (false !== $configuration) {
            array_splice($others, $configuration, 0, self::SECTION);
            $menu->reorderChildren($others);
        }
    }
}
