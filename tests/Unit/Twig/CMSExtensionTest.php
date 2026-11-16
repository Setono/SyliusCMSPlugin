<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Twig;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Block\RichText;
use Setono\SyliusCMSPlugin\Preview\FieldMarker;
use Setono\SyliusCMSPlugin\Preview\Preview;
use Setono\SyliusCMSPlugin\Preview\PreviewContext;
use Setono\SyliusCMSPlugin\Preview\PreviewTokenSigner;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Rendering\BlockView;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Rendering\TwigBlockRenderer;
use Setono\SyliusCMSPlugin\Tests\Unit\Twig\Fixture\Faq;
use Setono\SyliusCMSPlugin\Tests\Unit\Twig\Fixture\Quote;
use Setono\SyliusCMSPlugin\Twig\CMSExtension;
use Setono\SyliusCMSPlugin\Twig\CMSRuntime;
use Setono\SyliusCMSPlugin\Value\RichText as RichTextValue;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

final class CMSExtensionTest extends TestCase
{
    use ProphecyTrait;

    private const KEY = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f';

    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $this->requestStack = new RequestStack();
    }

    /**
     * @test
     */
    public function it_renders_a_heading_through_its_template(): void
    {
        self::assertSame('<h2 class="cms-heading">Hello</h2>', $this->render('{{ cms_blocks(blocks) }}', [self::heading('Hello')]));
    }

    /**
     * @test
     */
    public function it_renders_the_chosen_heading_level(): void
    {
        self::assertSame('<h3 class="cms-heading">Hello</h3>', $this->render('{{ cms_blocks(blocks) }}', [self::heading('Hello', 'h3')]));
    }

    /**
     * @test
     */
    public function it_renders_one_block(): void
    {
        self::assertSame('<h2 class="cms-heading">Hello</h2>', $this->render('{{ cms_block(blocks[0]) }}', [self::heading('Hello')]));
    }

    /**
     * @test
     */
    public function it_renders_blocks_in_order(): void
    {
        self::assertSame(
            "<h2 class=\"cms-heading\">One</h2>\n<h2 class=\"cms-heading\">Two</h2>",
            $this->render('{{ cms_blocks(blocks) }}', [self::heading('One'), self::heading('Two')]),
        );
    }

    /**
     * @test
     */
    public function it_escapes_text_fields(): void
    {
        self::assertSame(
            '<h2 class="cms-heading">&lt;b&gt;Tom &amp; Jerry&lt;/b&gt; &quot;say&quot; &#039;it&#039;</h2>',
            $this->render('{{ cms_blocks(blocks) }}', [self::heading('<b>Tom & Jerry</b> "say" \'it\'')]),
        );
    }

    /**
     * @test
     */
    public function it_renders_a_rich_text_block_with_its_html_as_it_is(): void
    {
        $richText = new RichText();
        $richText->content = new RichTextValue(['type' => 'doc', 'content' => []], '<p>A <strong>bold</strong> word &amp; more</p>');

        self::assertSame(
            '<div class="cms-rich-text"><p>A <strong>bold</strong> word &amp; more</p></div>',
            $this->render('{{ cms_blocks(blocks) }}', [new BlockView(self::KEY, 'rich_text', $richText, null, [])]),
        );
    }

    /**
     * @test
     */
    public function it_renders_an_empty_rich_text_block_as_an_empty_wrapper(): void
    {
        self::assertSame(
            '<div class="cms-rich-text"></div>',
            $this->render('{{ cms_blocks(blocks) }}', [new BlockView(self::KEY, 'rich_text', new RichText(), null, [])]),
        );
    }

    /**
     * @test
     */
    public function it_prints_a_missing_value_as_nothing(): void
    {
        self::assertSame('<h2 class="cms-heading"></h2>', $this->render('{{ cms_blocks(blocks) }}', [self::heading(null)]));
    }

    /**
     * @test
     */
    public function it_prints_numbers_and_stringable_values(): void
    {
        $object = new class() {
            public int $number = 42;

            public \Stringable $stringable;

            public function __construct()
            {
                $this->stringable = new class() implements \Stringable {
                    public function __toString(): string
                    {
                        return 'A & B';
                    }
                };
            }
        };

        self::assertSame('42|A &amp; B', $this->render('{{ cms_field(object, "number") }}|{{ cms_field(object, "stringable") }}', [], ['object' => $object]));
    }

    /**
     * @test
     */
    public function it_refuses_to_print_a_field_that_is_not_public(): void
    {
        $this->expectException(RuntimeError::class);
        $this->expectExceptionMessage('must be a public property');

        $this->render('{{ cms_field(object, "secret") }}', [], ['object' => new class() {
            private string $secret = 'hidden'; // @phpstan-ignore property.onlyWritten
        }]);
    }

    /**
     * @test
     */
    public function it_refuses_to_print_a_field_the_subject_does_not_have(): void
    {
        $this->expectException(RuntimeError::class);
        $this->expectExceptionMessage('"subtitle"');

        $this->render('{{ cms_field(blocks[0].block, "subtitle") }}', [self::heading('Hello')]);
    }

    /**
     * @test
     */
    public function it_refuses_to_print_a_value_it_cannot_print(): void
    {
        $this->expectException(RuntimeError::class);
        $this->expectExceptionMessage('which cms_field() can\'t print');

        $this->render('{{ cms_field(object, "value") }}', [], ['object' => new class() {
            /** @var list<string> */
            public array $value = ['a'];
        }]);
    }

    /**
     * @test
     */
    public function it_tells_templates_they_render_in_the_builders_preview(): void
    {
        $signer = new PreviewTokenSigner('secret', new MockClock());
        $this->requestStack->push(new Request([PreviewContext::QUERY_PARAMETER => $signer->sign(new Preview('0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e', 'WEB', 'en_US'))]));

        self::assertSame('builder', $this->render('{{ cms_preview_mode() }}', []));
    }

    /**
     * @test
     */
    public function it_shows_templates_no_preview_mode_for_shoppers(): void
    {
        $this->requestStack->push(new Request());

        // Assigned first, because older Twig, such as the lowest 3.7 allowed, compiles ?? to a defined test, which takes only a variable
        self::assertSame('none', $this->render('{% set mode = cms_preview_mode() %}{{ mode ?? "none" }}', []));
    }

    /**
     * @test
     */
    public function it_marks_a_text_field_in_the_builders_preview_so_it_can_be_typed_in_place(): void
    {
        self::assertSame(
            '<h2 class="cms-heading"><!--cms:field ' . self::KEY . ' text text-->Hello &amp; welcome<!--/cms:field ' . self::KEY . ' text--></h2>',
            $this->render('{{ cms_blocks(blocks) }}', [self::heading('Hello & welcome')], previewMode: RenderContext::PREVIEW_MODE_BUILDER),
        );
    }

    /**
     * @test
     */
    public function it_marks_a_rich_text_field_in_the_builders_preview(): void
    {
        $richText = new RichText();
        $richText->content = new RichTextValue(['type' => 'doc', 'content' => []], '<p>A <strong>bold</strong> word</p>');

        self::assertSame(
            '<div class="cms-rich-text"><!--cms:field ' . self::KEY . ' content rich_text--><p>A <strong>bold</strong> word</p><!--/cms:field ' . self::KEY . ' content--></div>',
            $this->render('{{ cms_blocks(blocks) }}', [new BlockView(self::KEY, 'rich_text', $richText, null, [])], previewMode: RenderContext::PREVIEW_MODE_BUILDER),
        );
    }

    /**
     * @test
     */
    public function it_marks_an_empty_field_in_the_builders_preview(): void
    {
        self::assertSame(
            '<h2 class="cms-heading"><!--cms:field ' . self::KEY . ' text text--><!--/cms:field ' . self::KEY . ' text--></h2>',
            $this->render('{{ cms_blocks(blocks) }}', [self::heading(null)], previewMode: RenderContext::PREVIEW_MODE_BUILDER),
        );
    }

    /**
     * @test
     */
    public function it_marks_a_textarea_field_but_not_other_field_types_or_fields_printed_without_cms_field(): void
    {
        $quote = new Quote();
        $quote->quote = "First line\nSecond line";
        $quote->author = 'Ann';

        self::assertSame(
            '<blockquote class="small"><p><!--cms:field ' . self::KEY . " quote textarea-->First line\nSecond line<!--/cms:field " . self::KEY . ' quote--></p><cite>Ann</cite></blockquote>',
            $this->render('{{ cms_blocks(blocks) }}', [new BlockView(self::KEY, 'quote', $quote, null, [])], previewMode: RenderContext::PREVIEW_MODE_BUILDER),
        );
    }

    /**
     * @test
     */
    public function it_marks_the_fields_of_a_child_its_parents_template_prints_with_the_childs_key(): void
    {
        $faq = new BlockView('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e50', 'faq', new Faq(), null, [self::heading('Question')]);

        self::assertSame(
            '<dl><dt><!--cms:field ' . self::KEY . ' text text-->Question<!--/cms:field ' . self::KEY . ' text--></dt></dl>',
            $this->render('{{ cms_blocks(blocks) }}', [$faq], previewMode: RenderContext::PREVIEW_MODE_BUILDER),
        );
    }

    /**
     * @test
     *
     * @dataProvider shopperModes
     *
     * @param RenderContext::PREVIEW_MODE_SHARE|null $previewMode
     */
    public function shoppers_and_share_links_get_fields_without_markers(?string $previewMode): void
    {
        self::assertSame('<h2 class="cms-heading">Hello</h2>', $this->render('{{ cms_blocks(blocks) }}', [self::heading('Hello')], previewMode: $previewMode));
    }

    /**
     * @return iterable<string, array{RenderContext::PREVIEW_MODE_SHARE|null}>
     */
    public static function shopperModes(): iterable
    {
        yield 'shopper' => [null];
        yield 'share link' => [RenderContext::PREVIEW_MODE_SHARE];
    }

    /**
     * @test
     */
    public function it_does_not_mark_a_field_of_something_that_is_not_a_block_being_rendered(): void
    {
        $object = new class() {
            public string $text = 'Hello';
        };

        self::assertSame('Hello|Hello', $this->render(
            '{{ cms_field(object, "text") }}|{% with { view: blocks[0] } %}{{ cms_field(object, "text") }}{% endwith %}',
            [self::heading('Hello')],
            ['object' => $object],
            RenderContext::PREVIEW_MODE_BUILDER,
        ));
    }

    /**
     * @test
     */
    public function it_does_not_mark_fields_without_the_render_context(): void
    {
        self::assertSame('Hello', trim($this->twig(['template' => '{{ cms_field(view.block, "text") }}'])->render('template', ['view' => self::heading('Hello')])));
    }

    /**
     * @test
     */
    public function it_needs_the_render_context_to_render_blocks(): void
    {
        $this->expectException(RuntimeError::class);
        $this->expectExceptionMessage('render context');

        $this->twig(['template' => '{{ cms_blocks(blocks) }}'])->render('template', ['blocks' => [self::heading('Hello')]]);
    }

    /**
     * @param list<BlockView> $blocks
     * @param array<string, mixed> $variables
     * @param RenderContext::PREVIEW_MODE_*|null $previewMode
     */
    private function render(string $template, array $blocks, array $variables = [], ?string $previewMode = null): string
    {
        $context = new RenderContext($this->prophesize(ChannelInterface::class)->reveal(), 'en_US', 'USD', null, '@guest', new \DateTimeImmutable(), [], $previewMode);

        return trim($this->twig(['template' => $template])->render('template', ['blocks' => $blocks, CMSRuntime::RENDER_CONTEXT => $context] + $variables));
    }

    /**
     * @param array<string, string> $templates
     */
    private function twig(array $templates): Environment
    {
        $filesystem = new FilesystemLoader();
        $filesystem->addPath(dirname(__DIR__, 3) . '/src/Resources/views', 'SetonoSyliusCMSPlugin');

        $templates += [
            'quote.html.twig' => '<blockquote class="{{ cms_field(block, "size") }}"><p>{{ cms_field(block, "quote") }}</p><cite>{{ block.author }}</cite></blockquote>',
            'faq.html.twig' => '<dl>{% for child in children %}<dt>{{ cms_field(child.block, "text") }}</dt>{% endfor %}</dl>',
        ];
        $twig = new Environment(new ChainLoader([new ArrayLoader($templates), $filesystem]), ['strict_variables' => true]);
        $twig->addExtension(new CMSExtension());

        $blockTypes = new BlockTypeRegistry(['heading' => Heading::class, 'rich_text' => RichText::class, 'quote' => Quote::class, 'faq' => Faq::class], new FieldReader());
        $renderer = new TwigBlockRenderer($twig, $blockTypes);
        $previewContext = new PreviewContext($this->requestStack, new PreviewTokenSigner('secret', new MockClock()));
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([
            CMSRuntime::class => static fn (): CMSRuntime => new CMSRuntime($renderer, new FieldMarker($blockTypes)),
            PreviewContext::class => static fn (): PreviewContext => $previewContext,
        ]));

        return $twig;
    }

    private static function heading(?string $text, string $level = 'h2'): BlockView
    {
        $heading = new Heading();
        $heading->text = $text;
        $heading->level = $level;

        return new BlockView(self::KEY, 'heading', $heading, null, []);
    }
}
