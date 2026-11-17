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
use Setono\SyliusCMSPlugin\Field\ChoiceHandler;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\MediaHandler;
use Setono\SyliusCMSPlugin\Field\MediaListHandler;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Field\RichTextHandler;
use Setono\SyliusCMSPlugin\Field\TextHandler;
use Setono\SyliusCMSPlugin\Field\ToggleHandler;
use Setono\SyliusCMSPlugin\Localization\FallbackChainInterface;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Media\MediaKind;
use Setono\SyliusCMSPlugin\Model\MediaItem;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Rendering\BlockHydrator;
use Setono\SyliusCMSPlugin\Rendering\BlockView;
use Setono\SyliusCMSPlugin\Rendering\BlockViewBuilder;
use Setono\SyliusCMSPlugin\Rendering\ReferenceLoader;
use Setono\SyliusCMSPlugin\Rendering\ReferenceLoaderInterface;
use Setono\SyliusCMSPlugin\Rendering\ReferenceMap;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Tests\Support\Conditions;
use Setono\SyliusCMSPlugin\Tests\Support\Links;
use Setono\SyliusCMSPlugin\Tests\Unit\Tree\Fixture\Gallery;
use Setono\SyliusCMSPlugin\Tree\ReferenceFinder;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\TreeValues;
use Setono\SyliusCMSPlugin\Value\RichText as RichTextValue;
use Sylius\Component\Core\Model\ChannelInterface;
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

    private static function image(): MediaItemInterface
    {
        $mediaItem = new MediaItem();
        $mediaItem->setKind(MediaKind::Image);

        return $mediaItem;
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

    private function builder(?ReferenceLoaderInterface $referenceLoader = null): BlockViewBuilder
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
        ]));

        $blockTypes = new BlockTypeRegistry(
            ['heading' => Heading::class, 'rich_text' => RichText::class, 'group' => Group::class, 'first_match' => FirstMatch::class, 'gallery' => Gallery::class],
            new FieldReader(),
            styles: ['group' => ['brand' => ['label' => 'app.cms.block_style.brand', 'class' => 'app-group--brand']]],
        );

        return new BlockViewBuilder(
            $blockTypes,
            new BlockHydrator($handlers, new FallbackValues($handlers, $fallbackChain->reveal())),
            Conditions::matcher(),
            new ReferenceFinder(new TreeValues($blockTypes, new LayoutRegistry([], new FieldReader())), $handlers),
            $referenceLoader ?? new ReferenceLoader(new ServiceLocator([])),
        );
    }

    private function context(string $localeCode): RenderContext
    {
        return new RenderContext($this->prophesize(ChannelInterface::class)->reveal(), $localeCode, 'USD', null, '@guest', new \DateTimeImmutable());
    }
}
