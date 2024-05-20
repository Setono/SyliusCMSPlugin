<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Application\EventSubscriber;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class RemoveOfficialSupportSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'sylius.menu.admin.main' => 'remove',
        ];
    }

    public function remove(MenuBuilderEvent $event): void
    {
        $event->getMenu()->removeChild('official_support');
    }
}
