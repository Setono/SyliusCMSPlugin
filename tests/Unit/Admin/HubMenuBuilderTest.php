<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Admin;

use Knp\Menu\ItemInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Admin\HubMenuBuilder;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class HubMenuBuilderTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<EntryRepositoryInterface> */
    private ObjectProphecy $entryRepository;

    private EventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        $this->entryRepository = $this->prophesize(EntryRepositoryInterface::class);
        $this->entryRepository->countEntries()->willReturn(7);
        $this->entryRepository->countEntries('page')->willReturn(3);

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
        self::assertSame('/admin/cms', $overview->getUri());
        self::assertSame('home', $overview->getLabelAttribute('icon'));

        self::assertSame('setono_sylius_cms.menu.admin.hub.entries', self::child($menu, 'entries')->getLabel());

        self::assertSame('setono_sylius_cms.menu.admin.hub.library', self::child($menu, 'library')->getLabel());
    }

    /**
     * @test
     */
    public function its_library_has_the_media_library(): void
    {
        $library = self::child($this->menu(), 'library');

        self::assertSame(['media'], array_keys($library->getChildren()));

        $media = self::child($library, 'media');
        self::assertSame('setono_sylius_cms.menu.admin.hub.media', $media->getLabel());
        self::assertSame('/admin/cms/media', $media->getUri());
        self::assertSame('images outline', $media->getLabelAttribute('icon'));
        self::assertTrue(MenuFactory::matcherOn('setono_sylius_cms_admin_media_index')->isCurrent($media));
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
        self::assertSame('/admin/cms/entries', $all->getUri());
        self::assertSame('list', $all->getLabelAttribute('icon'));
        self::assertSame(7, $all->getExtra('count'));

        $pages = self::child($entries, 'page');
        self::assertSame('setono_sylius_cms.menu.admin.hub.pages', $pages->getLabel());
        self::assertSame('/admin/cms/entries/page', $pages->getUri());
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
            $event->getMenu()->getChild('library')?->addChild('brand_assets', ['uri' => '/brand-assets']);
        });

        self::assertSame(['media', 'brand_assets'], array_keys(self::child($this->menu(), 'library')->getChildren()));
    }

    private function menu(): ItemInterface
    {
        return (new HubMenuBuilder(MenuFactory::create(), $this->eventDispatcher, $this->entryRepository->reveal()))->createMenu([]);
    }

    private static function child(ItemInterface $item, string $name): ItemInterface
    {
        $child = $item->getChild($name);
        self::assertNotNull($child, sprintf('The item "%s" has no child "%s"', $item->getName(), $name));

        return $child;
    }
}
