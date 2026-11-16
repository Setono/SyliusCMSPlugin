<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Rendering;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Field\ChoiceHandler;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\TextHandler;
use Setono\SyliusCMSPlugin\Field\ToggleHandler;
use Setono\SyliusCMSPlugin\Localization\FallbackChainInterface;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeMetadata;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Rendering\BlockHydrator;
use Setono\SyliusCMSPlugin\Rendering\BlockViewBuilder;
use Setono\SyliusCMSPlugin\Rendering\LayoutRenderer;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Tests\Support\Conditions;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Landing;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Standard;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgraderInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class LayoutRendererTest extends TestCase
{
    use ProphecyTrait;

    private const KEY = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f';

    /**
     * @test
     */
    public function it_renders_the_entry_in_the_layout_its_tree_names_with_that_layouts_values_the_blocks_and_the_contexts(): void
    {
        $page = new Page();
        $tree = new StoredTree(new StoredLayout('landing', 2, ['transparentHeader' => true]), [new StoredBlock(self::KEY, 'heading', 1, fields: ['text' => ['en_US' => 'Hello']])]);
        $context = $this->context(['entry' => $page]);

        $html = $this->renderer($page, $tree)->render($page, $context);

        self::assertSame(sprintf('landing|transparent|entry|context|%s:Hello|en_US', self::KEY), $html);
    }

    /**
     * @test
     */
    public function an_entry_whose_layout_was_removed_renders_in_the_default_layout_without_the_stored_values(): void
    {
        $page = new Page();

        $html = $this->renderer($page, new StoredTree(new StoredLayout('gone', 1, ['showTitle' => false]), []))->render($page, $this->context());

        self::assertSame('standard|title', $html);
    }

    /**
     * @test
     */
    public function an_entry_whose_layout_is_disabled_renders_in_the_default_layout_without_the_stored_values(): void
    {
        $page = new Page();

        $html = $this->renderer($page, new StoredTree(new StoredLayout('landing', 2, ['showTitle' => false]), []), disabled: ['page.landing'])->render($page, $this->context());

        self::assertSame('standard|title', $html);
    }

    /**
     * @test
     */
    public function an_entry_in_the_default_layout_gets_its_stored_values_and_one_without_a_layout_the_defaults(): void
    {
        $page = new Page();

        self::assertSame('standard|no title', $this->renderer($page, new StoredTree(new StoredLayout('standard', 1, ['showTitle' => false]), []))->render($page, $this->context()));
        self::assertSame('standard|title', $this->renderer($page, new StoredTree(null, []))->render($page, $this->context()));
    }

    /**
     * @test
     */
    public function an_entry_of_a_content_type_without_layouts_has_no_page_to_render(): void
    {
        $recipe = new Recipe();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The content type "recipe" has no layouts, so its entries have no page of their own to render');

        $this->renderer($recipe, new StoredTree(null, []), contentType: 'recipe')->render($recipe, $this->context());
    }

    /**
     * @param list<string> $disabled
     */
    private function renderer(Page|Recipe $entry, StoredTree $tree, array $disabled = [], string $contentType = 'page'): LayoutRenderer
    {
        $contentTypes = $this->prophesize(ContentTypeRegistryInterface::class);
        $contentTypes->getByEntry($entry)->willReturn(new ContentTypeMetadata($contentType, 'app.label', 1, '', [], $entry::class, PageTranslation::class, 'app.' . $contentType));

        $upgrader = $this->prophesize(StoredDataUpgraderInterface::class);
        $upgrader->readBlockTree($entry)->willReturn($tree);

        $fallbackChain = $this->prophesize(FallbackChainInterface::class);
        $fallbackChain->localesFor('en_US')->willReturn(['en_US']);
        $handlers = new FieldHandlerLocator(new ServiceLocator([
            TextHandler::class => static fn (): TextHandler => new TextHandler(),
            ChoiceHandler::class => fn (): ChoiceHandler => new ChoiceHandler($this->prophesize(TranslatorInterface::class)->reveal()),
            ToggleHandler::class => static fn (): ToggleHandler => new ToggleHandler(),
        ]));
        $hydrator = new BlockHydrator($handlers, new FallbackValues($handlers, $fallbackChain->reveal()));

        return new LayoutRenderer(
            $contentTypes->reveal(),
            new LayoutRegistry(['page' => ['standard' => Standard::class, 'landing' => Landing::class]], new FieldReader(), disabled: $disabled),
            $upgrader->reveal(),
            new BlockViewBuilder(new BlockTypeRegistry(['heading' => Heading::class], new FieldReader()), $hydrator, Conditions::matcher()),
            $hydrator,
            new Environment(new ArrayLoader([
                'standard.html.twig' => 'standard|{{ layout.showTitle ? "title" : "no title" }}',
                'landing.html.twig' => 'landing|{{ layout.transparentHeader ? "transparent" : "plain" }}|{{ entry is same as(context.entry) ? "entry" }}|{{ context.entry is defined ? "context" }}|{% for view in blocks %}{{ view.key }}:{{ view.block.text }}{% endfor %}|{{ _cms_render_context.localeCode }}',
            ])),
        );
    }

    /**
     * @param array<string, object> $hostPageContext
     */
    private function context(array $hostPageContext = []): RenderContext
    {
        return new RenderContext($this->prophesize(ChannelInterface::class)->reveal(), 'en_US', 'USD', null, '@guest', new \DateTimeImmutable(), $hostPageContext);
    }
}
