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

//        $header
//            ->addChild('consent_entries', [
//                'route' => 'setono_sylius_consent_management_admin_consent_entry_index',
//            ])
//            ->setLabel('setono_sylius_consent_management.menu.admin.main.consent_management.consent_entries')
//            ->setLabelAttribute('icon', 'list alternate outline')
//        ;
//
//        $header
//            ->addChild('services', [
//                'route' => 'setono_sylius_consent_management_admin_service_index',
//            ])
//            ->setLabel('setono_sylius_consent_management.menu.admin.main.consent_management.services')
//            ->setLabelAttribute('icon', 'cogs')
//        ;
//
//        $header
//            ->addChild('cookies', [
//                'route' => 'setono_sylius_consent_management_admin_cookie_index',
//            ])
//            ->setLabel('setono_sylius_consent_management.menu.admin.main.consent_management.cookies')
//            ->setLabelAttribute('icon', 'user secret')
//        ;
//
//        $header
//            ->addChild('widget_config', [
//                'route' => 'setono_sylius_consent_management_admin_widget_config_index',
//            ])
//            ->setLabel('setono_sylius_consent_management.menu.admin.main.consent_management.widget_configuration')
//            ->setLabelAttribute('icon', 'cog')
//        ;
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
