<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Rendering;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Block\Group;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Block\RichText;
use Setono\SyliusCMSPlugin\Field\ChoiceHandler;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\RichTextHandler;
use Setono\SyliusCMSPlugin\Field\TextHandler;
use Setono\SyliusCMSPlugin\Field\ToggleHandler;
use Setono\SyliusCMSPlugin\Localization\FallbackChainInterface;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Rendering\BlockHydrator;
use Setono\SyliusCMSPlugin\Rendering\BlockViewBuilder;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\RichText\RichTextSchema;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Value\RichText as RichTextValue;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Contracts\Translation\TranslatorInterface;

final class BlockViewBuilderTest extends TestCase
{
    use ProphecyTrait;

    private const A = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4a';

    private const B = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4b';

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
    public function a_style_code_that_is_no_longer_declared_resolves_to_no_class(): void
    {
        $views = $this->builder()->build([
            new StoredBlock(self::A, 'group', 1, style: 'retired'),
            new StoredBlock(self::B, 'heading', 1, style: 'dark'),
        ], $this->context('en_US'));

        self::assertNull($views[0]->styleClass);
        self::assertNull($views[1]->styleClass, 'Another block type\'s style is no style');
    }

    private function builder(): BlockViewBuilder
    {
        $fallbackChain = $this->prophesize(FallbackChainInterface::class);
        $fallbackChain->localesFor('da_DK')->willReturn(['da_DK', 'en_US']);
        $fallbackChain->localesFor('en_US')->willReturn(['en_US']);

        $translator = $this->prophesize(TranslatorInterface::class);

        $handlers = new FieldHandlerLocator(new ServiceLocator([
            TextHandler::class => static fn (): TextHandler => new TextHandler(),
            RichTextHandler::class => static fn (): RichTextHandler => new RichTextHandler(new RichTextSchema()),
            ChoiceHandler::class => static fn (): ChoiceHandler => new ChoiceHandler($translator->reveal()),
            ToggleHandler::class => static fn (): ToggleHandler => new ToggleHandler(),
        ]));

        return new BlockViewBuilder(
            new BlockTypeRegistry(
                ['heading' => Heading::class, 'rich_text' => RichText::class, 'group' => Group::class],
                new FieldReader(),
                styles: ['group' => ['brand' => ['label' => 'app.cms.block_style.brand', 'class' => 'app-group--brand']]],
            ),
            new BlockHydrator($handlers, new FallbackValues($handlers, $fallbackChain->reveal())),
        );
    }

    private function context(string $localeCode): RenderContext
    {
        return new RenderContext($this->prophesize(ChannelInterface::class)->reveal(), $localeCode, 'USD', null, '@guest', new \DateTimeImmutable());
    }
}
