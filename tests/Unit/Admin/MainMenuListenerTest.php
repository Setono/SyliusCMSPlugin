<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Admin;

use Knp\Menu\ItemInterface;
use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Admin\MainMenuListener;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

final class MainMenuListenerTest extends TestCase
{
    /**
     * @test
     */
    public function it_adds_one_content_item_that_opens_the_hub_before_the_configuration(): void
    {
        $menu = self::syliusMenu(['catalog', 'marketing', 'configuration', 'official_support']);

        self::assertSame(['catalog', 'marketing', 'setono_sylius_cms', 'configuration', 'official_support'], array_keys($menu->getChildren()));

        $section = $menu->getChild('setono_sylius_cms');
        self::assertNotNull($section);
        self::assertSame('setono_sylius_cms.menu.admin.main.content.header', $section->getLabel());
        self::assertSame(['content'], array_keys($section->getChildren()));

        $content = $section->getChild('content');
        self::assertNotNull($content);
        self::assertSame('setono_sylius_cms.menu.admin.main.content.content', $content->getLabel());
        self::assertSame('/setono_sylius_cms_admin_overview', $content->getUri());
        self::assertSame('pencil alternate', $content->getLabelAttribute('icon'));
    }

    /**
     * @test
     */
    public function it_adds_the_item_last_in_a_menu_without_the_configuration(): void
    {
        $menu = self::syliusMenu(['catalog', 'marketing']);

        self::assertSame(['catalog', 'marketing', 'setono_sylius_cms'], array_keys($menu->getChildren()));
    }

    /**
     * @test
     */
    public function it_marks_the_item_current_on_every_page_of_the_hub_and_on_no_other(): void
    {
        $content = self::syliusMenu(['configuration'])->getChild('setono_sylius_cms')?->getChild('content');
        self::assertNotNull($content);

        self::assertTrue(MenuFactory::matcherOn('setono_sylius_cms_admin_overview')->isCurrent($content));
        self::assertTrue(MenuFactory::matcherOn('setono_sylius_cms_admin_entry_index', ['contentType' => 'page'])->isCurrent($content));
        self::assertTrue(MenuFactory::matcherOn('setono_sylius_cms_admin_entry_index_all')->isCurrent($content));
        self::assertFalse(MenuFactory::matcherOn('sylius_admin_product_index')->isCurrent($content));
        self::assertFalse(MenuFactory::matcherOn('app_setono_sylius_cms_admin_report')->isCurrent($content));
    }

    /**
     * @param list<string> $sections
     */
    private static function syliusMenu(array $sections): ItemInterface
    {
        $factory = MenuFactory::create();
        $menu = $factory->createItem('root');
        foreach ($sections as $section) {
            $menu->addChild($section);
        }

        (new MainMenuListener())(new MenuBuilderEvent($factory, $menu));

        return $menu;
    }
}
