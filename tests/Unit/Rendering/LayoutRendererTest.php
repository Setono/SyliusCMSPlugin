<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Rendering;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Field\ChoiceHandler;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\MediaHandler;
use Setono\SyliusCMSPlugin\Field\MediaListHandler;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Field\TextHandler;
use Setono\SyliusCMSPlugin\Field\ToggleHandler;
use Setono\SyliusCMSPlugin\Localization\FallbackChainInterface;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Media\MediaKind;
use Setono\SyliusCMSPlugin\Model\MediaItem;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeMetadata;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Rendering\BlockHydrator;
use Setono\SyliusCMSPlugin\Rendering\BlockReferences;
use Setono\SyliusCMSPlugin\Rendering\BlockViewBuilder;
use Setono\SyliusCMSPlugin\Rendering\LayoutRenderer;
use Setono\SyliusCMSPlugin\Rendering\ReferenceLoader;
use Setono\SyliusCMSPlugin\Rendering\ReferenceLoaderInterface;
use Setono\SyliusCMSPlugin\Rendering\ReferenceMap;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Tests\Support\Bindings;
use Setono\SyliusCMSPlugin\Tests\Support\Conditions;
use Setono\SyliusCMSPlugin\Tests\Unit\Condition\Fixture\ProductAvailable;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Landing;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Standard;
use Setono\SyliusCMSPlugin\Tests\Unit\Tree\Fixture\Gallery;
use Setono\SyliusCMSPlugin\Tests\Unit\Tree\Fixture\Hero;
use Setono\SyliusCMSPlugin\Tree\ReferenceFinder;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Setono\SyliusCMSPlugin\Tree\TreeValues;
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

    private const HERO = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d01';

    private const COVER = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d02';

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
    public function it_loads_the_references_of_the_layout_the_blocks_and_their_conditions_together_before_it_renders(): void
    {
        $page = new Page();
        $hero = new MediaItem();
        $hero->setKind(MediaKind::Image);
        $hero->setName('Summer');
        $tree = new StoredTree(new StoredLayout('hero', 1, ['image' => self::HERO]), [new StoredBlock(self::KEY, 'gallery', 1, fields: ['cover' => self::COVER], conditions: [['kind' => 'product_available', 'negate' => true, 'params' => ['product' => 'T_SHIRT']]])]);
        $context = $this->context();

        $loader = $this->prophesize(ReferenceLoaderInterface::class);
        $loader->load(Argument::that(static fn (iterable $references): bool => [
            ['media_item', self::HERO, true],
            ['media_item', self::COVER, true],
            ['product', 'T_SHIRT', false],
        ] === self::references($references)), $context)
            ->willReturn(new ReferenceMap(['media_item' => [self::HERO => $hero]]))
            ->shouldBeCalledOnce()
        ;

        self::assertSame('hero|Summer|empty', $this->renderer($page, $tree, referenceLoader: $loader->reveal())->render($page, $context));
    }

    /**
     * @test
     */
    public function it_renders_an_entry_whose_layout_was_removed_in_the_default_layout_without_the_stored_values(): void
    {
        $page = new Page();

        $html = $this->renderer($page, new StoredTree(new StoredLayout('gone', 1, ['showTitle' => false]), []))->render($page, $this->context());

        self::assertSame('standard|title', $html);
    }

    /**
     * @test
     */
    public function it_renders_an_entry_whose_layout_is_disabled_in_the_default_layout_without_the_stored_values(): void
    {
        $page = new Page();

        $html = $this->renderer($page, new StoredTree(new StoredLayout('landing', 2, ['showTitle' => false]), []), disabled: ['page.landing'])->render($page, $this->context());

        self::assertSame('standard|title', $html);
    }

    /**
     * @test
     */
    public function it_gives_an_entry_in_the_default_layout_its_stored_values_and_one_without_a_layout_the_defaults(): void
    {
        $page = new Page();

        self::assertSame('standard|no title', $this->renderer($page, new StoredTree(new StoredLayout('standard', 1, ['showTitle' => false]), []))->render($page, $this->context()));
        self::assertSame('standard|title', $this->renderer($page, new StoredTree(null, []))->render($page, $this->context()));
    }

    /**
     * @test
     */
    public function it_has_no_page_to_render_for_an_entry_of_a_content_type_without_layouts(): void
    {
        $recipe = new Recipe();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The content type "recipe" has no layouts, so its entries have no page of their own to render');

        $this->renderer($recipe, new StoredTree(null, []), contentType: 'recipe')->render($recipe, $this->context());
    }

    /**
     * @param iterable<mixed> $references
     *
     * @return list<array{string, string, bool}>
     */
    private static function references(iterable $references): array
    {
        $found = [];
        foreach ($references as $reference) {
            self::assertInstanceOf(Reference::class, $reference);
            $found[] = [$reference->type, $reference->id, $reference->dependency];
        }

        return $found;
    }

    /**
     * @param list<string> $disabled
     */
    private function renderer(Page|Recipe $entry, StoredTree $tree, array $disabled = [], string $contentType = 'page', ?ReferenceLoaderInterface $referenceLoader = null): LayoutRenderer
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
            MediaHandler::class => static fn (): MediaHandler => new MediaHandler(),
            MediaListHandler::class => static fn (): MediaListHandler => new MediaListHandler(),
        ]));
        $hydrator = new BlockHydrator($handlers, new FallbackValues($handlers, $fallbackChain->reveal()), Bindings::resolver());
        $blockTypes = new BlockTypeRegistry(['heading' => Heading::class, 'gallery' => Gallery::class], new FieldReader());
        $layouts = new LayoutRegistry(['page' => ['standard' => Standard::class, 'landing' => Landing::class, 'hero' => Hero::class]], new FieldReader(), disabled: $disabled);
        $referenceFinder = new ReferenceFinder(new TreeValues($blockTypes, $layouts), $handlers);
        $referenceLoader ??= new ReferenceLoader(new ServiceLocator([]));

        return new LayoutRenderer(
            $contentTypes->reveal(),
            $layouts,
            $upgrader->reveal(),
            new BlockViewBuilder($blockTypes, $hydrator, $conditions = Conditions::matcher(['product_available' => new ProductAvailable()]), new BlockReferences($blockTypes, $conditions, $referenceFinder), $referenceLoader, $upgrader->reveal()),
            $hydrator,
            new Environment(new ArrayLoader([
                'standard.html.twig' => 'standard|{{ layout.showTitle ? "title" : "no title" }}',
                'landing.html.twig' => 'landing|{{ layout.transparentHeader ? "transparent" : "plain" }}|{{ entry is same as(context.entry) ? "entry" }}|{{ context.entry is defined ? "context" }}|{% for view in blocks %}{{ view.key }}:{{ view.block.text }}{% endfor %}|{{ _cms_render_context.localeCode }}',
                'hero.html.twig' => 'hero|{{ layout.image.name }}|{% for view in blocks %}{{ view.block.cover is null ? "empty" }}{% endfor %}',
            ])),
            $referenceFinder,
            $referenceLoader,
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
