<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Admin;

use Knp\Menu\ItemInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Admin\MainMenuListener;
use Setono\SyliusCMSPlugin\Problem\PresetProblemFinderInterface;
use Setono\SyliusCMSPlugin\Problem\ProblemCount;
use Setono\SyliusCMSPlugin\Problem\ProblemCountProviderInterface;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;

final class MainMenuListenerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_adds_one_content_item_that_opens_the_hub_before_the_configuration(): void
    {
        $menu = $this->syliusMenu(['catalog', 'marketing', 'configuration', 'official_support']);

        self::assertSame(['catalog', 'marketing', 'setono_sylius_cms', 'configuration', 'official_support'], array_keys($menu->getChildren()));

        $section = $menu->getChild('setono_sylius_cms');
        self::assertNotNull($section);
        self::assertSame('setono_sylius_cms.menu.admin.main.content.header', $section->getLabel());
        self::assertSame(['content'], array_keys($section->getChildren()));

        $content = $section->getChild('content');
        self::assertNotNull($content);
        self::assertSame('setono_sylius_cms.menu.admin.main.content.content', $content->getLabel());
        self::assertSame('/admin/cms', $content->getUri());
        self::assertSame('pencil alternate', $content->getLabelAttribute('icon'));
        self::assertSame([], $content->getLinkAttributes(), 'Nothing needs attention, so it has no badge');
    }

    /**
     * @test
     */
    public function it_adds_the_item_last_in_a_menu_without_the_configuration(): void
    {
        $menu = $this->syliusMenu(['catalog', 'marketing']);

        self::assertSame(['catalog', 'marketing', 'setono_sylius_cms'], array_keys($menu->getChildren()));
    }

    /**
     * @test
     */
    public function it_marks_the_item_current_on_every_page_of_the_hub_and_on_no_other(): void
    {
        $content = $this->syliusMenu(['configuration'])->getChild('setono_sylius_cms')?->getChild('content');
        self::assertNotNull($content);

        self::assertTrue(MenuFactory::matcherOn('setono_sylius_cms_admin_overview')->isCurrent($content));
        self::assertTrue(MenuFactory::matcherOn('setono_sylius_cms_admin_entry_index', ['contentType' => 'page'])->isCurrent($content));
        self::assertTrue(MenuFactory::matcherOn('setono_sylius_cms_admin_entry_index_all')->isCurrent($content));
        self::assertFalse(MenuFactory::matcherOn('sylius_admin_product_index')->isCurrent($content));
        self::assertFalse(MenuFactory::matcherOn('app_setono_sylius_cms_admin_report')->isCurrent($content));
    }

    /**
     * @test
     */
    public function it_counts_the_errors_and_warnings_that_need_attention_in_the_badge_marked_as_an_error_when_there_is_one(): void
    {
        $content = $this->syliusMenu(['configuration'], new ProblemCount(1, 2))->getChild('setono_sylius_cms')?->getChild('content');
        self::assertNotNull($content);

        self::assertSame([
            'data-cms-problems' => 'error',
            'data-cms-problem-count' => '3',
            'title' => '1 error, 2 warnings',
        ], $content->getLinkAttributes());
    }

    /**
     * @test
     */
    public function it_marks_the_badge_as_a_warning_when_only_warnings_need_attention(): void
    {
        $content = $this->syliusMenu(['configuration'], new ProblemCount(0, 1))->getChild('setono_sylius_cms')?->getChild('content');
        self::assertNotNull($content);

        self::assertSame([
            'data-cms-problems' => 'warning',
            'data-cms-problem-count' => '1',
            'title' => 'No errors, 1 warning',
        ], $content->getLinkAttributes());
    }

    /**
     * A preset with blocks of removed types is a warning too, and presets aren't entries, so theirs are counted apart
     *
     * @test
     */
    public function it_adds_the_problems_of_presets_to_the_badge(): void
    {
        $content = $this->syliusMenu(['configuration'], new ProblemCount(1, 0), new ProblemCount(0, 2))->getChild('setono_sylius_cms')?->getChild('content');
        self::assertNotNull($content);

        self::assertSame([
            'data-cms-problems' => 'error',
            'data-cms-problem-count' => '3',
            'title' => '1 error, 2 warnings',
        ], $content->getLinkAttributes());
    }

    /**
     * @param list<string> $sections
     */
    private function syliusMenu(array $sections, ProblemCount $problems = new ProblemCount(), ProblemCount $presetProblems = new ProblemCount()): ItemInterface
    {
        $factory = MenuFactory::create();
        $menu = $factory->createItem('root');
        foreach ($sections as $section) {
            $menu->addChild($section);
        }

        $problemCounts = $this->prophesize(ProblemCountProviderInterface::class);
        $problemCounts->getTotal()->willReturn($problems);

        $translator = new Translator('en');
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addResource('yaml', dirname(__DIR__, 3) . '/src/Resources/translations/messages.en.yaml', 'en');

        $presets = $this->prophesize(PresetProblemFinderInterface::class);
        $presets->count()->willReturn($presetProblems);

        (new MainMenuListener($problemCounts->reveal(), $presets->reveal(), $translator))(new MenuBuilderEvent($factory, $menu));

        return $menu;
    }
}
