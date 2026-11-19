<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Admin;

use Knp\Menu\ItemInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Admin\HubMenuBuilder;
use Setono\SyliusCMSPlugin\Collection\Collections;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Location;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Shop;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\ShopTranslation;
use Setono\SyliusCMSPlugin\Tests\Unit\Seo\Fixture\Post;
use Setono\SyliusCMSPlugin\Tests\Unit\Seo\Fixture\PostTranslation;
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
        // All entries leaves out those of a content type switched off
        $this->entryRepository->countEntries(null, ['shop'])->willReturn(7);
        $this->entryRepository->countEntries('page')->willReturn(3);
        $this->entryRepository->countEntries('snippet')->willReturn(4);
        $this->entryRepository->countEntries('post')->willReturn(2);
        $this->entryRepository->countEntries('location')->willReturn(5);
        $this->entryRepository->countEntries('recipe')->willReturn(6);

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
    public function it_has_the_media_library_the_presets_and_transfer_in_its_library(): void
    {
        $library = self::child($this->menu(), 'library');

        self::assertSame(['media', 'presets', 'transfer'], array_keys($library->getChildren()));

        $media = self::child($library, 'media');
        self::assertSame('setono_sylius_cms.menu.admin.hub.media', $media->getLabel());
        self::assertSame('/admin/cms/media', $media->getUri());
        self::assertSame('images outline', $media->getLabelAttribute('icon'));
        self::assertTrue(MenuFactory::matcherOn('setono_sylius_cms_admin_media_index')->isCurrent($media));

        $presets = self::child($library, 'presets');
        self::assertSame('setono_sylius_cms.menu.admin.hub.presets', $presets->getLabel());
        self::assertSame('/admin/cms/presets', $presets->getUri());
        self::assertSame('clone outline', $presets->getLabelAttribute('icon'));
        self::assertTrue(MenuFactory::matcherOn('setono_sylius_cms_admin_preset_index')->isCurrent($presets));

        $transfer = self::child($library, 'transfer');
        self::assertSame('setono_sylius_cms.menu.admin.hub.transfer', $transfer->getLabel());
        self::assertSame('/admin/cms/transfer', $transfer->getUri());
        self::assertSame('exchange', $transfer->getLabelAttribute('icon'));
        self::assertTrue(MenuFactory::matcherOn('setono_sylius_cms_admin_transfer')->isCurrent($transfer));
        // An import's page is one of the Transfer screen's
        self::assertTrue(MenuFactory::matcherOn('setono_sylius_cms_admin_import_show', ['uuid' => '0193a5be-0000-7000-8000-000000000001'])->isCurrent($transfer));
    }

    /**
     * @test
     */
    public function it_lists_all_entries_the_pages_and_the_snippets_with_how_many_there_are(): void
    {
        $entries = self::child($this->menu(), 'entries');

        self::assertSame(['all', 'page', 'snippet', 'post', 'location'], array_keys($entries->getChildren()));

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

        $snippets = self::child($entries, 'snippet');
        self::assertSame('setono_sylius_cms.menu.admin.hub.snippets', $snippets->getLabel());
        self::assertSame('/admin/cms/entries/snippet', $snippets->getUri());
        self::assertSame('puzzle piece', $snippets->getLabelAttribute('icon'));
        self::assertSame(4, $snippets->getExtra('count'));
    }

    /**
     * A collection is all entries of a content type other than the built-in ones. The shipped ones come first, then a
     * project's own, and a collection's category content type is reached through its collection's item. A content type
     * switched off has no item. See "Navigation" in docs/spec/admin.md
     *
     * @test
     */
    public function it_lists_an_item_per_collection_with_how_many_entries_it_has_the_shipped_ones_first(): void
    {
        $entries = self::child($this->menu(), 'entries');

        $posts = self::child($entries, 'post');
        self::assertSame('app.post', $posts->getLabel());
        self::assertSame(['setono_sylius_cms.collection.post', 'setono_sylius_cms.content_type_plural.post'], $posts->getExtra('names'));
        self::assertSame('/admin/cms/entries/post', $posts->getUri());
        self::assertSame('folder outline', $posts->getLabelAttribute('icon'));
        self::assertSame(2, $posts->getExtra('count'));
    }

    /**
     * A collection with categories lists both, switched by All, its entries and its categories, so its item counts both
     * and is current on each of the three lists. See "Navigation" in docs/spec/admin.md
     *
     * @test
     */
    public function it_lists_a_collection_with_categories_with_its_categories(): void
    {
        $locations = self::child(self::child($this->menu(), 'entries'), 'location');

        self::assertSame('app.cms.content_type.location', $locations->getLabel());
        self::assertSame('/admin/cms/collections/location', $locations->getUri());
        self::assertSame(11, $locations->getExtra('count'));
        self::assertTrue(MenuFactory::matcherOn('setono_sylius_cms_admin_collection_index', ['contentType' => 'location'])->isCurrent($locations));
        self::assertTrue(MenuFactory::matcherOn('setono_sylius_cms_admin_entry_index', ['contentType' => 'location'])->isCurrent($locations));
        self::assertTrue(MenuFactory::matcherOn('setono_sylius_cms_admin_entry_index', ['contentType' => 'recipe'])->isCurrent($locations));
        self::assertFalse(MenuFactory::matcherOn('setono_sylius_cms_admin_entry_index', ['contentType' => 'post'])->isCurrent($locations));
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

        self::assertSame(['media', 'presets', 'transfer', 'brand_assets'], array_keys(self::child($this->menu(), 'library')->getChildren()));
    }

    private function menu(): ItemInterface
    {
        // Recipe is the category content type of Location's collection, and Shop is switched off
        $contentTypes = new ContentTypeRegistry([
            'recipe' => ['model' => Recipe::class, 'translation' => null, 'resource' => 'app.recipe'],
            'location' => ['model' => Location::class, 'translation' => null, 'resource' => 'app.location'],
            'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
            'snippet' => ['model' => Snippet::class, 'translation' => null, 'resource' => 'setono_sylius_cms.snippet'],
            'post' => ['model' => Post::class, 'translation' => PostTranslation::class, 'resource' => 'setono_sylius_cms.post'],
            'shop' => ['model' => Shop::class, 'translation' => ShopTranslation::class, 'resource' => 'app.shop'],
        ], disabled: ['shop']);

        return (new HubMenuBuilder(MenuFactory::create(), $this->eventDispatcher, $this->entryRepository->reveal(), $contentTypes, new Collections($contentTypes)))->createMenu([]);
    }

    private static function child(ItemInterface $item, string $name): ItemInterface
    {
        $child = $item->getChild($name);
        self::assertNotNull($child, sprintf('The item "%s" has no child "%s"', $item->getName(), $name));

        return $child;
    }
}
