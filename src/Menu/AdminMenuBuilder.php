<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Menu;

use Knp\Menu\ItemInterface;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

final class AdminMenuBuilder
{
    public const MENU_ITEM_KEY = 'setono_sylius_cms';

    public function addSection(MenuBuilderEvent $event): void
    {
        $header = $this->getHeader($event->getMenu());

        $header
            ->addChild('assets', [
                'route' => 'setono_sylius_cms_admin_asset_index',
            ])
            ->setLabel('setono_sylius_cms.menu.admin.main.cms.assets')
            ->setLabelAttribute('icon', 'list alternate outline')
        ;

        $header
            ->addChild('blocks', [
                'route' => 'setono_sylius_cms_admin_block_index',
            ])
            ->setLabel('setono_sylius_cms.menu.admin.main.cms.blocks')
            ->setLabelAttribute('icon', 'clone outline')
        ;

        $header
            ->addChild('carousels', [
                'route' => 'setono_sylius_cms_admin_carousel_index',
            ])
            ->setLabel('setono_sylius_cms.menu.admin.main.cms.carousels')
            ->setLabelAttribute('icon', 'list alternate outline')
        ;

        $header
            ->addChild('pages', [
                'route' => 'setono_sylius_cms_admin_page_index',
            ])
            ->setLabel('setono_sylius_cms.menu.admin.main.cms.pages')
            ->setLabelAttribute('icon', 'file alternate outline')
        ;

        $header
            ->addChild('templates', [
                'route' => 'setono_sylius_cms_admin_template_index',
            ])
            ->setLabel('setono_sylius_cms.menu.admin.main.cms.templates')
            ->setLabelAttribute('icon', 'columns')
        ;
    }

    private function getHeader(ItemInterface $menu): ItemInterface
    {
        $header = $menu->getChild(self::MENU_ITEM_KEY);
        if (null !== $header) {
            return $header;
        }

        return $menu->addChild(self::MENU_ITEM_KEY)
            ->setLabel('setono_sylius_cms.menu.admin.main.cms.header');
    }
}
