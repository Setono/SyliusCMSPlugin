<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Admin;

use Knp\Menu\ItemInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Admin\EntryListQueryInterface;
use Setono\SyliusCMSPlugin\Admin\HubMenuBuilder;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class HubMenuBuilderTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<EntryListQueryInterface> */
    private ObjectProphecy $entryListQuery;

    private EventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        $this->entryListQuery = $this->prophesize(EntryListQueryInterface::class);
        $this->entryListQuery->count(null)->willReturn(7);
        $this->entryListQuery->count('page')->willReturn(3);

        $this->eventDispatcher = new EventDispatcher();
    }

    /**
     * @test
     */
    public function it_has_the_overview_then_the_entries_and_the_library(): void
    {
        $menu = $this->menu();

        self::assertSame(['overview', 'entries', 'library'], array_keys($menu->getChildren()));

        $overview = self::child($menu, 'overview');
        self::assertSame('setono_sylius_cms.menu.admin.hub.overview', $overview->getLabel());
        self::assertSame('/setono_sylius_cms_admin_overview', $overview->getUri());
        self::assertSame('home', $overview->getLabelAttribute('icon'));

        self::assertSame('setono_sylius_cms.menu.admin.hub.entries', self::child($menu, 'entries')->getLabel());

        $library = self::child($menu, 'library');
        self::assertSame('setono_sylius_cms.menu.admin.hub.library', $library->getLabel());
        self::assertSame([], $library->getChildren());
    }

    /**
     * @test
     */
    public function it_lists_all_entries_and_the_pages_with_how_many_there_are(): void
    {
        $entries = self::child($this->menu(), 'entries');

        self::assertSame(['all', 'page'], array_keys($entries->getChildren()));

        $all = self::child($entries, 'all');
        self::assertSame('setono_sylius_cms.menu.admin.hub.all_entries', $all->getLabel());
        self::assertSame('/setono_sylius_cms_admin_entry_index_all', $all->getUri());
        self::assertSame('list', $all->getLabelAttribute('icon'));
        self::assertSame(7, $all->getExtra('count'));

        $pages = self::child($entries, 'page');
        self::assertSame('setono_sylius_cms.menu.admin.hub.pages', $pages->getLabel());
        self::assertSame('/setono_sylius_cms_admin_entry_index?contentType=page', $pages->getUri());
        self::assertSame('file alternate outline', $pages->getLabelAttribute('icon'));
        self::assertSame(3, $pages->getExtra('count'));
    }

    /**
     * @test
     */
    public function it_marks_the_list_shown_current(): void
    {
        $entries = self::child($this->menu(), 'entries');

        $onPages = MenuFactory::matcherOn('setono_sylius_cms_admin_entry_index', ['contentType' => 'page']);
        self::assertTrue($onPages->isCurrent(self::child($entries, 'page')));
        self::assertFalse($onPages->isCurrent(self::child($entries, 'all')));

        $onAnnouncements = MenuFactory::matcherOn('setono_sylius_cms_admin_entry_index', ['contentType' => 'announcement']);
        self::assertFalse($onAnnouncements->isCurrent(self::child($entries, 'page')));
    }

    /**
     * @test
     */
    public function it_lets_listeners_add_items(): void
    {
        $this->eventDispatcher->addListener(HubMenuBuilder::EVENT, static function (MenuBuilderEvent $event): void {
            $event->getMenu()->getChild('library')?->addChild('media', ['uri' => '/media']);
        });

        self::assertSame(['media'], array_keys(self::child($this->menu(), 'library')->getChildren()));
    }

    private function menu(): ItemInterface
    {
        return (new HubMenuBuilder(MenuFactory::create(), $this->eventDispatcher, $this->entryListQuery->reveal()))->createMenu([]);
    }

    private static function child(ItemInterface $item, string $name): ItemInterface
    {
        $child = $item->getChild($name);
        self::assertNotNull($child, sprintf('The item "%s" has no child "%s"', $item->getName(), $name));

        return $child;
    }
}
