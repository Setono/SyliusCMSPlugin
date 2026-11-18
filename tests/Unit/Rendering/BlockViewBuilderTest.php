<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Rendering;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Block\FirstMatch;
use Setono\SyliusCMSPlugin\Block\Group;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Block\RichText;
use Setono\SyliusCMSPlugin\Block\Snippet as SnippetBlock;
use Setono\SyliusCMSPlugin\Field\ChoiceHandler;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\MediaHandler;
use Setono\SyliusCMSPlugin\Field\MediaListHandler;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Field\RichTextHandler;
use Setono\SyliusCMSPlugin\Field\SnippetHandler;
use Setono\SyliusCMSPlugin\Field\TextHandler;
use Setono\SyliusCMSPlugin\Field\ToggleHandler;
use Setono\SyliusCMSPlugin\Localization\FallbackChainInterface;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Media\MediaKind;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\MediaItem;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Rendering\BlockReferences;
use Setono\SyliusCMSPlugin\Rendering\BlockView;
use Setono\SyliusCMSPlugin\Rendering\BlockViewBuilder;
use Setono\SyliusCMSPlugin\Rendering\FailureHandlerInterface;
use Setono\SyliusCMSPlugin\Rendering\ReferenceLoader;
use Setono\SyliusCMSPlugin\Rendering\ReferenceLoaderInterface;
use Setono\SyliusCMSPlugin\Rendering\ReferenceMap;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Tests\Support\Blocks;
use Setono\SyliusCMSPlugin\Tests\Support\Conditions;
use Setono\SyliusCMSPlugin\Tests\Support\Links;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;
use Setono\SyliusCMSPlugin\Tests\Unit\Condition\Fixture\ProductAvailable;
use Setono\SyliusCMSPlugin\Tests\Unit\Rendering\Fixture\Unbuildable;
use Setono\SyliusCMSPlugin\Tests\Unit\Tree\Fixture\Gallery;
use Setono\SyliusCMSPlugin\Tree\ReferenceFinder;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Setono\SyliusCMSPlugin\Tree\TreeValues;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgraderInterface;
use Setono\SyliusCMSPlugin\Value\RichText as RichTextValue;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Contracts\Translation\TranslatorInterface;

final class BlockViewBuilderTest extends TestCase
{
    use ProphecyTrait;

    private const A = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4a';

    private const B = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4b';

    private const C = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4c';

    private const D = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4d';

    private const COVER = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d01';

    private const IMAGE = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d02';

    private const DELETED = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d03';

    /**
     * Bound values can't be typed in place, so the view says which fields show one. See "Working in the preview" in
     * docs/spec/builder.md
     *
     * @test
     */
    public function it_names_the_fields_that_show_a_binding(): void
    {
        $bound = new StoredBlock(self::A, 'heading', 1);
        $bound->fields = ['text' => ['$bind' => 'product.name', '$params' => [], '$fallback' => ['en_US' => 'this product']]];
        $typed = new StoredBlock(self::B, 'heading', 1);
        $typed->fields = ['text' => ['en_US' => 'Hello']];

        $views = $this->builder()->build([$bound, $typed], $this->context('en_US'));

        self::assertSame(['text'], $views[0]->bound);
        self::assertInstanceOf(Heading::class, $views[0]->block);
        self::assertSame('this product', $views[0]->block->text);
        self::assertSame([], $views[1]->bound);
    }

    /**
     * @test
     */
    public function it_builds_a_view_per_block_with_the_hydrated_block_type_object(): void
    {
        $block = new StoredBlock(self::A, 'heading', 1);
        $block->fields = ['text' => ['en_US' => 'Hello', 'da_DK' => 'Hej'], 'level' => 'h3'];

        $views = $this->builder()->build([$block], $this->context('da_DK'));

        self::assertCount(1, $views);
        self::assertSame(self::A, $views[0]->key);
        self::assertSame('heading', $views[0]->type);
        self::assertNull($views[0]->styleClass);
        self::assertSame([], $views[0]->children);
        self::assertInstanceOf(Heading::class, $views[0]->block);
        self::assertSame('Hej', $views[0]->block->text);
        self::assertSame('h3', $views[0]->block->level);
    }

    /**
     * @test
     */
    public function it_falls_back_through_the_fallback_chain_for_a_missing_or_empty_translatable_value(): void
    {
        $missing = new StoredBlock(self::A, 'heading', 1);
        $missing->fields = ['text' => ['en_US' => 'Hello']];
        $empty = new StoredBlock(self::B, 'heading', 1);
        $empty->fields = ['text' => ['en_US' => 'Hello', 'da_DK' => '']];

        $views = $this->builder()->build([$missing, $empty], $this->context('da_DK'));

        self::assertInstanceOf(Heading::class, $views[0]->block);
        self::assertSame('Hello', $views[0]->block->text);
        self::assertInstanceOf(Heading::class, $views[1]->block);
        self::assertSame('Hello', $views[1]->block->text);
    }

    /**
     * @test
     */
    public function it_falls_back_past_an_empty_rich_text_document_and_a_value_that_does_not_fit(): void
    {
        $hello = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hello']]]]];
        $empty = new StoredBlock(self::A, 'rich_text', 1);
        $empty->fields = ['content' => ['en_US' => $hello, 'da_DK' => ['type' => 'doc', 'content' => [['type' => 'paragraph']]]]];
        $notFitting = new StoredBlock(self::B, 'rich_text', 1);
        $notFitting->fields = ['content' => ['en_US' => $hello, 'da_DK' => '<p>Hej</p>']];

        $views = $this->builder()->build([$empty, $notFitting], $this->context('da_DK'));

        foreach ($views as $view) {
            self::assertInstanceOf(RichText::class, $view->block);
            self::assertInstanceOf(RichTextValue::class, $view->block->content);
            self::assertSame('<p>Hello</p>', $view->block->content->html);
        }
    }

    /**
     * @test
     */
    public function it_leaves_the_property_default_for_missing_values_and_values_that_do_not_fit(): void
    {
        $noValues = new StoredBlock(self::A, 'heading', 1);
        $wrongValues = new StoredBlock(self::B, 'heading', 1);
        $wrongValues->fields = ['text' => 'not a locale map', 'level' => 'h6'];

        $views = $this->builder()->build([$noValues, $wrongValues], $this->context('en_US'));

        foreach ($views as $view) {
            self::assertInstanceOf(Heading::class, $view->block);
            self::assertNull($view->block->text);
            self::assertSame('h2', $view->block->level);
        }
    }

    /**
     * @test
     */
    public function it_hydrates_a_later_field_after_one_that_is_missing_or_does_not_fit(): void
    {
        $missing = new StoredBlock(self::A, 'heading', 1);
        $missing->fields = ['level' => 'h3'];
        $notFitting = new StoredBlock(self::B, 'heading', 1);
        $notFitting->fields = ['text' => ['en_US' => 42], 'level' => 'h4'];

        $views = $this->builder()->build([$missing, $notFitting], $this->context('en_US'));

        self::assertInstanceOf(Heading::class, $views[0]->block);
        self::assertSame('h3', $views[0]->block->level);
        self::assertInstanceOf(Heading::class, $views[1]->block);
        self::assertNull($views[1]->block->text);
        self::assertSame('h4', $views[1]->block->level);
    }

    /**
     * @test
     */
    public function it_leaves_out_blocks_of_a_type_the_code_no_longer_has(): void
    {
        $views = $this->builder()->build([new StoredBlock(self::A, 'removed', 1), new StoredBlock(self::B, 'heading', 1)], $this->context('en_US'));

        self::assertCount(1, $views);
        self::assertSame(self::B, $views[0]->key);
    }

    /**
     * @test
     */
    public function it_builds_the_children_too(): void
    {
        $parent = new StoredBlock(self::A, 'heading', 1);
        $parent->children = [new StoredBlock(self::B, 'heading', 1)];

        $views = $this->builder()->build([$parent], $this->context('en_US'));

        self::assertCount(1, $views[0]->children);
        self::assertSame(self::B, $views[0]->children[0]->key);
    }

    /**
     * @test
     */
    public function it_gives_a_view_the_class_of_its_block_style_from_the_block_type_or_the_config(): void
    {
        $views = $this->builder()->build([
            new StoredBlock(self::A, 'group', 1, style: 'dark'),
            new StoredBlock(self::B, 'group', 1, style: 'brand'),
        ], $this->context('en_US'));

        self::assertSame('cms-group--dark', $views[0]->styleClass);
        self::assertSame('app-group--brand', $views[1]->styleClass);
    }

    /**
     * @test
     */
    public function it_resolves_a_style_code_that_is_no_longer_declared_to_no_class(): void
    {
        $views = $this->builder()->build([
            new StoredBlock(self::A, 'group', 1, style: 'retired'),
            new StoredBlock(self::B, 'heading', 1, style: 'dark'),
        ], $this->context('en_US'));

        self::assertNull($views[0]->styleClass);
        self::assertNull($views[1]->styleClass, 'Another block type\'s style is no style');
    }

    /**
     * @test
     */
    public function it_leaves_out_a_block_whose_visibility_conditions_do_not_match_with_its_children(): void
    {
        $hidden = self::onlyIn(new StoredBlock(self::A, 'group', 1), 'da_DK');
        $hidden->children = [new StoredBlock(self::B, 'heading', 1)];
        $shown = self::onlyIn(new StoredBlock(self::C, 'group', 1), 'en_US');
        $shown->children = [new StoredBlock(self::D, 'heading', 1)];

        $views = $this->builder()->build([$hidden, $shown], $this->context('en_US'));

        self::assertCount(1, $views);
        self::assertSame(self::C, $views[0]->key);
        self::assertCount(1, $views[0]->children);
        self::assertSame(self::D, $views[0]->children[0]->key);
    }

    /**
     * A template gets only the children that show
     *
     * @test
     */
    public function it_leaves_out_the_children_whose_visibility_conditions_do_not_match(): void
    {
        $parent = new StoredBlock(self::A, 'group', 1);
        $parent->children = [
            self::onlyIn(new StoredBlock(self::B, 'heading', 1), 'da_DK'),
            new StoredBlock(self::C, 'heading', 1),
            self::onlyIn(new StoredBlock(self::D, 'heading', 1), 'en_US'),
        ];

        $views = $this->builder()->build([$parent], $this->context('en_US'));

        self::assertSame([self::C, self::D], self::keys($views[0]->children));
    }

    /**
     * @test
     */
    public function it_shows_only_the_first_child_of_a_first_match_block_whose_conditions_match(): void
    {
        $firstMatch = new StoredBlock(self::A, 'first_match', 1);
        $firstMatch->children = [
            self::onlyIn(new StoredBlock(self::B, 'heading', 1), 'da_DK'),
            new StoredBlock('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4e', 'removed', 1),
            self::onlyIn(new StoredBlock(self::C, 'heading', 1), 'en_US'),
            new StoredBlock(self::D, 'heading', 1),
        ];

        $english = $this->builder()->build([$firstMatch], $this->context('en_US'));
        $danish = $this->builder()->build([$firstMatch], $this->context('da_DK'));
        $german = $this->builder()->build([$firstMatch], $this->context('de_DE'));

        self::assertSame([self::C], self::keys($english[0]->children), 'A block of a type the code no longer has is skipped');
        self::assertSame([self::B], self::keys($danish[0]->children));
        self::assertSame([self::D], self::keys($german[0]->children), 'A child without conditions is the fallback');
    }

    /**
     * @test
     */
    public function it_shows_nothing_inside_a_first_match_block_whose_children_all_hide(): void
    {
        $firstMatch = new StoredBlock(self::A, 'first_match', 1);
        $firstMatch->children = [self::onlyIn(new StoredBlock(self::B, 'heading', 1), 'da_DK')];

        $views = $this->builder()->build([$firstMatch], $this->context('en_US'));

        self::assertCount(1, $views);
        self::assertSame([], $views[0]->children);
    }

    /**
     * Only a First match block's own children are limited, not the list it's in nor the children of the one that shows
     *
     * @test
     */
    public function it_keeps_all_the_children_of_the_child_a_first_match_block_shows(): void
    {
        $group = new StoredBlock(self::B, 'group', 1);
        $group->children = [new StoredBlock(self::C, 'heading', 1), new StoredBlock(self::D, 'heading', 1)];
        $firstMatch = new StoredBlock(self::A, 'first_match', 1);
        $firstMatch->children = [$group];

        $views = $this->builder()->build([$firstMatch, new StoredBlock('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4e', 'heading', 1)], $this->context('en_US'));

        self::assertCount(2, $views);
        self::assertSame([self::C, self::D], self::keys($views[0]->children[0]->children));
    }

    /**
     * @test
     */
    public function it_loads_every_reference_in_the_blocks_in_one_pass_before_it_hydrates_them(): void
    {
        $cover = self::image();
        $image = self::image();
        $context = $this->context('en_US');

        $child = new StoredBlock(self::B, 'gallery', 1, fields: ['images' => [self::IMAGE, self::DELETED]]);
        $parent = new StoredBlock(self::A, 'gallery', 1, fields: ['cover' => self::COVER], children: [$child]);

        $loader = $this->prophesize(ReferenceLoaderInterface::class);
        $loader->load(Argument::that(static fn (iterable $references): bool => [
            ['media_item', self::COVER, true],
            ['media_item', self::IMAGE, true],
            ['media_item', self::DELETED, true],
        ] === self::references($references)), $context)
            ->willReturn(new ReferenceMap(['media_item' => [self::COVER => $cover, self::IMAGE => $image]]))
            ->shouldBeCalledOnce()
        ;

        $views = $this->builder($loader->reveal())->build([$parent], $context);

        self::assertInstanceOf(Gallery::class, $views[0]->block);
        self::assertSame($cover, $views[0]->block->cover);
        self::assertInstanceOf(Gallery::class, $views[0]->children[0]->block);
        self::assertNotNull($views[0]->children[0]->block->images);
        self::assertSame([$image], [...$views[0]->children[0]->block->images]);
    }

    /**
     * A project's condition kind can take a product, which loads with the fields' references, so the conditions of the
     * blocks that render match against the records the shopper may see
     *
     * @test
     */
    public function it_loads_the_references_in_visibility_conditions_parameters_in_the_same_pass(): void
    {
        $cover = self::image();
        $tShirt = $this->prophesize(ProductInterface::class)->reveal();
        $context = $this->context('en_US');

        $child = new StoredBlock(self::B, 'heading', 1, conditions: [self::productAvailable('MUG')]);
        $parent = new StoredBlock(self::A, 'gallery', 1, fields: ['cover' => self::COVER], conditions: [self::productAvailable('T_SHIRT')], children: [$child]);
        $removed = new StoredBlock(self::C, 'removed', 1, conditions: [self::productAvailable('CAP')]);

        $loader = $this->prophesize(ReferenceLoaderInterface::class);
        $loader->load(Argument::that(static fn (iterable $references): bool => [
            ['media_item', self::COVER, true],
            ['product', 'T_SHIRT', false],
            ['product', 'MUG', false],
        ] === self::references($references)), $context)
            ->willReturn(new ReferenceMap(['media_item' => [self::COVER => $cover], 'product' => ['T_SHIRT' => $tShirt]]))
            ->shouldBeCalledOnce()
        ;

        $views = $this->builder($loader->reveal())->build([$parent, $removed], $context);

        self::assertSame([self::A], self::keys($views));
        self::assertSame([], $views[0]->children, 'The mug the shopper may not see hides its block');
    }

    /**
     * @test
     */
    public function it_hydrates_from_the_reference_map_it_is_given_without_loading_references(): void
    {
        $cover = self::image();

        $loader = $this->prophesize(ReferenceLoaderInterface::class);
        $loader->load(Argument::cetera())->shouldNotBeCalled();

        $views = $this->builder($loader->reveal())->build(
            [new StoredBlock(self::A, 'gallery', 1, fields: ['cover' => self::COVER])],
            $this->context('en_US'),
            new ReferenceMap(['media_item' => [self::COVER => $cover]]),
        );

        self::assertInstanceOf(Gallery::class, $views[0]->block);
        self::assertSame($cover, $views[0]->block->cover);
    }

    /**
     * @test
     */
    public function it_builds_a_snippet_block_s_children_from_the_referenced_snippet_s_blocks_with_the_tree_s_context(): void
    {
        $footer = self::snippet(self::heading(self::B, 'Opening hours'));
        $context = $this->context('en_US');

        $views = $this->builder()->build([Blocks::snippet(self::A, $footer)], $context, self::snippetsMap($footer));

        self::assertSame([self::A], self::keys($views));
        self::assertSame('snippet', $views[0]->type);
        self::assertInstanceOf(SnippetBlock::class, $views[0]->block);
        self::assertSame($footer, $views[0]->block->snippet);
        self::assertSame([self::B], self::keys($views[0]->children));
        self::assertInstanceOf(Heading::class, $views[0]->children[0]->block);
        self::assertSame('Opening hours', $views[0]->children[0]->block->text);
    }

    /**
     * @test
     */
    public function it_builds_no_children_for_a_snippet_block_whose_snippet_the_shopper_may_not_see(): void
    {
        $unpublished = self::snippet(self::heading(self::B, 'Opening hours'));

        $views = $this->builder()->build([Blocks::snippet(self::A, $unpublished)], $this->context('en_US'), self::snippetsMap());

        self::assertSame([self::A], self::keys($views));
        self::assertInstanceOf(SnippetBlock::class, $views[0]->block);
        self::assertNull($views[0]->block->snippet);
        self::assertSame([], $views[0]->children);
    }

    /**
     * @test
     */
    public function it_skips_a_snippet_the_second_time_it_would_render_inside_itself(): void
    {
        $a = self::snippet(self::heading(self::B, 'A'));
        $b = self::snippet(self::heading(self::D, 'B'));
        $a->setBlockTree(new StoredTree(null, [self::heading(self::B, 'A'), Blocks::snippet(self::C, $b)]));
        $b->setBlockTree(new StoredTree(null, [self::heading(self::D, 'B'), Blocks::snippet(self::A, $a)]));

        // A page showing A shows A, B inside it, and A inside that no more
        $views = $this->builder()->build([Blocks::snippet('page-a', $a)], $this->context('en_US'), self::snippetsMap($a, $b));

        $inA = $views[0]->children;
        self::assertSame([self::B, self::C], self::keys($inA));
        $inB = $inA[1]->children;
        self::assertSame([self::D, self::A], self::keys($inB));
        self::assertSame([], $inB[1]->children, 'A inside B inside A is skipped');
    }

    /**
     * @test
     */
    public function it_skips_a_snippet_inside_the_snippet_whose_tree_it_builds(): void
    {
        $a = self::snippet();
        $b = self::snippet();
        $a->setBlockTree(new StoredTree(null, [self::heading(self::B, 'A'), Blocks::snippet(self::C, $b), Blocks::snippet(self::D, $a)]));
        $b->setBlockTree(new StoredTree(null, [Blocks::snippet(self::A, $a)]));

        // A in a slot: B inside it shows, but A doesn't show inside B, nor inside itself
        $views = $this->builder()->build($a->getBlockTree()->blocks, $this->context('en_US'), self::snippetsMap($a, $b), $a->getUuid());

        self::assertSame([self::B, self::C, self::D], self::keys($views));
        self::assertSame([self::A], self::keys($views[1]->children));
        self::assertSame([], $views[1]->children[0]->children);
        self::assertSame([], $views[2]->children);
    }

    /**
     * So a block that throws while rendering is logged with the entry an editor fixes it in
     *
     * @test
     */
    public function it_gives_each_view_the_uuid_of_the_entry_whose_tree_holds_its_block_including_inside_a_snippet_block(): void
    {
        $footer = self::snippet(self::heading(self::C, 'Opening hours'));
        $page = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e40';

        $views = $this->builder()->build([self::heading(self::A, 'About us'), Blocks::snippet(self::B, $footer)], $this->context('en_US'), self::snippetsMap($footer), $page);

        self::assertSame([$page, $page], array_map(static fn (BlockView $view): ?string => $view->entry, $views));
        self::assertSame($footer->getUuid(), $views[1]->children[0]->entry);
    }

    /**
     * Its fields are hydrated before any template renders, so a field handler or a condition kind that throws makes the
     * block throw while it renders, as a template would. Its neighbours still get views
     *
     * @test
     */
    public function it_hands_a_block_that_throws_while_its_view_is_built_to_the_failure_handler_and_builds_the_others(): void
    {
        $page = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e40';
        $failures = $this->prophesize(FailureHandlerInterface::class);
        $failures->blockFailed(self::B, 'unbuildable', $page, Argument::which('getMessage', Unbuildable::MESSAGE))->shouldBeCalledOnce();

        $views = $this->builder(failures: $failures->reveal())->build(
            [self::heading(self::A, 'Before'), new StoredBlock(self::B, 'unbuildable', 1), new StoredBlock(self::C, 'group', 1, children: [self::heading(self::D, 'After')])],
            $this->context('en_US'),
            entry: $page,
        );

        self::assertSame([self::A, self::C], self::keys($views));
        self::assertSame([self::D], self::keys($views[1]->children));
    }

    /**
     * The builder's preview shows a block's error on the block when it renders (Preview\PreviewBlockRenderer)
     *
     * @test
     */
    public function it_throws_what_a_block_threw_while_its_view_was_built_in_the_builders_preview(): void
    {
        $failures = $this->prophesize(FailureHandlerInterface::class);
        $failures->blockFailed(Argument::cetera())->shouldNotBeCalled();

        $this->expectExceptionMessage(Unbuildable::MESSAGE);

        $this->builder(failures: $failures->reveal())->build(
            [new StoredBlock(self::B, 'unbuildable', 1)],
            $this->context('en_US')->withPreviewMode(RenderContext::PREVIEW_MODE_BUILDER),
        );
    }

    /**
     * Such as the blocks the builder sends to be previewed
     *
     * @test
     */
    public function it_gives_views_of_blocks_that_are_no_entry_s_no_entry(): void
    {
        $views = $this->builder()->build([self::heading(self::A, 'About us')], $this->context('en_US'));

        self::assertNull($views[0]->entry);
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

    private static function snippet(StoredBlock ...$blocks): SnippetInterface
    {
        $snippet = new Snippet();
        $snippet->setBlockTree(new StoredTree(null, array_values($blocks)));

        return $snippet;
    }

    private static function heading(string $key, string $text): StoredBlock
    {
        return new StoredBlock($key, 'heading', 1, fields: ['text' => ['en_US' => $text]]);
    }

    /**
     * The references of the snippets the shopper may see, as the reference loader loads them
     */
    private static function snippetsMap(SnippetInterface ...$snippets): ReferenceMap
    {
        $entries = [];
        foreach ($snippets as $snippet) {
            $entries[$snippet->getUuid()] = $snippet;
        }

        return new ReferenceMap(['entry' => $entries]);
    }

    private static function image(): MediaItemInterface
    {
        $mediaItem = new MediaItem();
        $mediaItem->setKind(MediaKind::Image);

        return $mediaItem;
    }

    /**
     * @return array<string, mixed>
     */
    private static function productAvailable(string $code): array
    {
        return ['kind' => 'product_available', 'negate' => false, 'params' => ['product' => $code]];
    }

    private static function onlyIn(StoredBlock $block, string $localeCode): StoredBlock
    {
        $block->conditions = [['kind' => 'locale_is', 'negate' => false, 'params' => ['locales' => [$localeCode]]]];

        return $block;
    }

    /**
     * @param list<BlockView> $views
     *
     * @return list<string>
     */
    private static function keys(array $views): array
    {
        return array_map(static fn (BlockView $view): string => $view->key, $views);
    }

    private function builder(?ReferenceLoaderInterface $referenceLoader = null, ?FailureHandlerInterface $failures = null): BlockViewBuilder
    {
        $fallbackChain = $this->prophesize(FallbackChainInterface::class);
        $fallbackChain->localesFor('da_DK')->willReturn(['da_DK', 'en_US']);
        $fallbackChain->localesFor('en_US')->willReturn(['en_US']);

        $translator = $this->prophesize(TranslatorInterface::class);

        $handlers = new FieldHandlerLocator(new ServiceLocator([
            TextHandler::class => static fn (): TextHandler => new TextHandler(),
            RichTextHandler::class => Links::richTextHandler(...),
            ChoiceHandler::class => static fn (): ChoiceHandler => new ChoiceHandler($translator->reveal()),
            ToggleHandler::class => static fn (): ToggleHandler => new ToggleHandler(),
            MediaHandler::class => static fn (): MediaHandler => new MediaHandler(),
            MediaListHandler::class => static fn (): MediaListHandler => new MediaListHandler(),
            SnippetHandler::class => static fn (): SnippetHandler => new SnippetHandler(),
        ]));

        $blockTypes = new BlockTypeRegistry(
            ['heading' => Heading::class, 'rich_text' => RichText::class, 'group' => Group::class, 'first_match' => FirstMatch::class, 'gallery' => Gallery::class, 'snippet' => SnippetBlock::class, 'unbuildable' => Unbuildable::class],
            new FieldReader(),
            styles: ['group' => ['brand' => ['label' => 'app.cms.block_style.brand', 'class' => 'app-group--brand']]],
        );

        return new BlockViewBuilder(
            $blockTypes,
            Rendering::hydrator($handlers, new FallbackValues($handlers, $fallbackChain->reveal())),
            $conditions = Conditions::matcher(['product_available' => new ProductAvailable()]),
            new BlockReferences($blockTypes, $conditions, new ReferenceFinder(new TreeValues($blockTypes, new LayoutRegistry([], new FieldReader())), $handlers)),
            $referenceLoader ?? new ReferenceLoader(new ServiceLocator([])),
            $this->upgrader(),
            $failures ?? $this->prophesize(FailureHandlerInterface::class)->reveal(),
        );
    }

    /**
     * Reads an entry's tree as it's stored, which is up to date
     */
    private function upgrader(): StoredDataUpgraderInterface
    {
        $upgrader = $this->prophesize(StoredDataUpgraderInterface::class);
        $upgrader->readBlockTree(Argument::type(EntryInterface::class))->will(static function (array $arguments): StoredTree {
            $entry = $arguments[0];
            self::assertInstanceOf(EntryInterface::class, $entry);

            return $entry->getBlockTree();
        });

        return $upgrader->reveal();
    }

    private function context(string $localeCode): RenderContext
    {
        return new RenderContext($this->prophesize(ChannelInterface::class)->reveal(), $localeCode, 'USD', null, '@guest', new \DateTimeImmutable());
    }
}
