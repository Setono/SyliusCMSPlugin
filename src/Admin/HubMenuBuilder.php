<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Knp\Menu\FactoryInterface;
use Knp\Menu\ItemInterface;
use Setono\SyliusCMSPlugin\Collection\Collections;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeMetadata;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The Content hub's own side menu, the KnpMenu menu "setono_sylius_cms.admin.hub": Overview, Entries, with an item
 * per collection after Pages and Snippets, which lists its categories too, and Library. Items show a count from their "count" extra. Later tickets
 * and projects add their items in a listener of the event this dispatches, the way Sylius's admin menu is extended.
 * See "Navigation" in docs/spec/admin.md
 */
final readonly class HubMenuBuilder
{
    public const EVENT = 'setono_sylius_cms.menu.admin.hub';

    public function __construct(
        private FactoryInterface $factory,
        private EventDispatcherInterface $eventDispatcher,
        private EntryRepositoryInterface $entryRepository,
        private ContentTypeRegistryInterface $contentTypes,
        private Collections $collections,
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
            // The entries of a content type switched off are hidden from the admin
            ->setExtra('count', $this->entryRepository->countEntries(null, $this->disabledContentTypes()))
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

        foreach ($this->collections->all() as $collection) {
            $code = $collection->code();
            $categories = $collection->categories?->code;

            $entries
                ->addChild($code, null === $categories ? [
                    'route' => 'setono_sylius_cms_admin_entry_index',
                    'routeParameters' => ['contentType' => $code],
                ] : [
                    // Its entries and its categories, switched by All and a button for each, in lists of their own
                    'route' => 'setono_sylius_cms_admin_collection_index',
                    'routeParameters' => ['contentType' => $code],
                    'extras' => ['routes' => [
                        ['route' => 'setono_sylius_cms_admin_entry_index', 'parameters' => ['contentType' => $code]],
                        ['route' => 'setono_sylius_cms_admin_entry_index', 'parameters' => ['contentType' => $categories]],
                    ]],
                ])
                ->setLabel($collection->entries->label)
                // Named by the collection's name when there's one, such as Blog, or else by the plural, as its list
                // is, and otherwise by the label
                ->setExtra('names', [sprintf('setono_sylius_cms.collection.%s', $code), sprintf('setono_sylius_cms.content_type_plural.%s', $code)])
                ->setLabelAttribute('icon', 'folder outline')
                ->setExtra('count', $this->entryRepository->countEntries($code) + (null === $categories ? 0 : $this->entryRepository->countEntries($categories)))
            ;
        }

        $library = $menu->addChild('library')->setLabel('setono_sylius_cms.menu.admin.hub.library');

        // Transfer joins with its ticket
        $library
            ->addChild('media', ['route' => 'setono_sylius_cms_admin_media_index'])
            ->setLabel('setono_sylius_cms.menu.admin.hub.media')
            ->setLabelAttribute('icon', 'images outline')
        ;

        $library
            ->addChild('presets', ['route' => 'setono_sylius_cms_admin_preset_index'])
            ->setLabel('setono_sylius_cms.menu.admin.hub.presets')
            ->setLabelAttribute('icon', 'clone outline')
        ;

        $this->eventDispatcher->dispatch(new MenuBuilderEvent($this->factory, $menu), self::EVENT);

        return $menu;
    }

    /**
     * @return list<string>
     */
    private function disabledContentTypes(): array
    {
        return array_values(array_map(
            static fn (ContentTypeMetadata $contentType): string => $contentType->code,
            array_filter($this->contentTypes->all(), static fn (ContentTypeMetadata $contentType): bool => $contentType->disabled),
        ));
    }
}
