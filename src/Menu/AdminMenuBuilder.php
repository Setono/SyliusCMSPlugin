<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Menu;

use Knp\Menu\ItemInterface;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

final class AdminMenuBuilder
{
    public function addSection(MenuBuilderEvent $event): void
    {
        $header = $this->getHeader($event->getMenu());

        $header
            ->addChild('templates', [
                'route' => 'setono_sylius_cms_admin_template_index',
            ])
            ->setLabel('setono_sylius_cms.menu.admin.main.content_management.templates')
            ->setLabelAttribute('icon', 'columns')
        ;

        $header
            ->addChild('pages', [
                'route' => 'setono_sylius_cms_admin_page_index',
            ])
            ->setLabel('setono_sylius_cms.menu.admin.main.content_management.pages')
            ->setLabelAttribute('icon', 'file alternate outline')
        ;

        $header
            ->addChild('blocks', [
                'route' => 'setono_sylius_cms_admin_block_index',
            ])
            ->setLabel('setono_sylius_cms.menu.admin.main.content_management.blocks')
            ->setLabelAttribute('icon', 'clone outline')
        ;

        $header
            ->addChild('views', [
                'route' => 'setono_sylius_cms_admin_view_index',
            ])
            ->setLabel('setono_sylius_cms.menu.admin.main.content_management.views')
            ->setLabelAttribute('icon', 'sliders horizontal')
        ;

        $header
            ->addChild('assets', [
                'route' => 'setono_sylius_cms_admin_asset_index',
            ])
            ->setLabel('setono_sylius_cms.menu.admin.main.content_management.assets')
            ->setLabelAttribute('icon', 'list alternate outline')
        ;

        $header
            ->addChild('carousels', [
                'route' => 'setono_sylius_cms_admin_carousel_index',
            ])
            ->setLabel('setono_sylius_cms.menu.admin.main.content_management.carousels')
            ->setLabelAttribute('icon', 'list alternate outline')
        ;
    }

    private function getHeader(ItemInterface $menu): ItemInterface
    {
        $header = $menu->getChild('content_management');
        if (null !== $header) {
            return $header;
        }

        $header = $menu->addChild('content_management')
            ->setLabel('setono_sylius_cms.menu.admin.main.content_management.header')
        ;

        return $header;
    }
}
