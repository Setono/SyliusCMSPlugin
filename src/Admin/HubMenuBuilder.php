<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Knp\Menu\FactoryInterface;
use Knp\Menu\ItemInterface;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The Content hub's own side menu, the KnpMenu menu "setono_sylius_cms.admin.hub": Overview, Entries and Library.
 * Items show a count from their "count" extra. Later tickets and projects add their items in a listener of
 * the event this dispatches, the way Sylius's admin menu is extended. See "Navigation" in docs/spec/admin.md
 */
final readonly class HubMenuBuilder
{
    public const EVENT = 'setono_sylius_cms.menu.admin.hub';

    public function __construct(
        private FactoryInterface $factory,
        private EventDispatcherInterface $eventDispatcher,
        private EntryRepositoryInterface $entryRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function createMenu(array $options): ItemInterface
    {
        $menu = $this->factory->createItem('root');

        $menu
            ->addChild('overview', ['route' => 'setono_sylius_cms_admin_overview'])
            ->setLabel('setono_sylius_cms.menu.admin.hub.overview')
            ->setLabelAttribute('icon', 'home')
        ;

        $entries = $menu->addChild('entries')->setLabel('setono_sylius_cms.menu.admin.hub.entries');

        $entries
            ->addChild('all', ['route' => 'setono_sylius_cms_admin_entry_index_all'])
            ->setLabel('setono_sylius_cms.menu.admin.hub.all_entries')
            ->setLabelAttribute('icon', 'list')
            ->setExtra('count', $this->entryRepository->countEntries())
        ;

        $entries
            ->addChild('page', ['route' => 'setono_sylius_cms_admin_entry_index', 'routeParameters' => ['contentType' => 'page']])
            ->setLabel('setono_sylius_cms.menu.admin.hub.pages')
            ->setLabelAttribute('icon', 'file alternate outline')
            ->setExtra('count', $this->entryRepository->countEntries('page'))
        ;

        $entries
            ->addChild('snippet', ['route' => 'setono_sylius_cms_admin_entry_index', 'routeParameters' => ['contentType' => SnippetInterface::CONTENT_TYPE]])
            ->setLabel('setono_sylius_cms.menu.admin.hub.snippets')
            ->setLabelAttribute('icon', 'puzzle piece')
            ->setExtra('count', $this->entryRepository->countEntries(SnippetInterface::CONTENT_TYPE))
        ;

        $library = $menu->addChild('library')->setLabel('setono_sylius_cms.menu.admin.hub.library');

        // Presets and Transfer join with their tickets
        $library
            ->addChild('media', ['route' => 'setono_sylius_cms_admin_media_index'])
            ->setLabel('setono_sylius_cms.menu.admin.hub.media')
            ->setLabelAttribute('icon', 'images outline')
        ;

        $this->eventDispatcher->dispatch(new MenuBuilderEvent($this->factory, $menu), self::EVENT);

        return $menu;
    }
}
