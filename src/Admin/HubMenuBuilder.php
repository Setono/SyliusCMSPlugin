<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Knp\Menu\FactoryInterface;
use Knp\Menu\ItemInterface;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeMetadata;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The Content hub's own side menu, the KnpMenu menu "setono_sylius_cms.admin.hub": Overview, Entries, with an item
 * per collection after Pages and Snippets, and Library. Items show a count from their "count" extra. Later tickets
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

        foreach ($this->collections() as $contentType) {
            $entries
                ->addChild($contentType->code, ['route' => 'setono_sylius_cms_admin_entry_index', 'routeParameters' => ['contentType' => $contentType->code]])
                ->setLabel($contentType->label)
                // Named by the plural, as its list is, when there's one, and otherwise by the label
                ->setExtra('plural', sprintf('setono_sylius_cms.content_type_plural.%s', $contentType->code))
                ->setLabelAttribute('icon', 'folder outline')
                ->setExtra('count', $this->entryRepository->countEntries($contentType->code))
            ;
        }

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

    /**
     * The collections, each the entries of a content type other than the built-in ones, the shipped ones first and then
     * a project's own, each in the order they're registered. A content type switched off has none, and a collection's
     * category content type is reached through its collection
     *
     * @return list<ContentTypeMetadata>
     */
    private function collections(): array
    {
        $enabled = $this->contentTypes->enabled();
        $categoryTypes = array_filter(array_map(static fn (ContentTypeMetadata $contentType): ?string => $contentType->categoryType, $enabled), static fn (?string $categoryType): bool => null !== $categoryType);
        $collections = array_filter($enabled, static fn (ContentTypeMetadata $contentType): bool => !$contentType->isBuiltIn() && !in_array($contentType->modelClass, $categoryTypes, true));

        return [
            ...array_filter($collections, static fn (ContentTypeMetadata $contentType): bool => $contentType->isShipped()),
            ...array_filter($collections, static fn (ContentTypeMetadata $contentType): bool => !$contentType->isShipped()),
        ];
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
