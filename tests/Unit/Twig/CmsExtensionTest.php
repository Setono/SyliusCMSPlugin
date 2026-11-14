<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Twig;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Rendering\BlockView;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Rendering\TwigBlockRenderer;
use Setono\SyliusCMSPlugin\Twig\CmsExtension;
use Setono\SyliusCMSPlugin\Twig\CmsRuntime;
use Sylius\Component\Core\Model\ChannelInterface;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

final class CmsExtensionTest extends TestCase
{
    use ProphecyTrait;

    private const KEY = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f';

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
    public function it_needs_the_render_context_to_render_blocks(): void
    {
        $this->expectException(RuntimeError::class);
        $this->expectExceptionMessage('render context');

        $this->twig(['template' => '{{ cms_blocks(blocks) }}'])->render('template', ['blocks' => [self::heading('Hello')]]);
    }

    /**
     * @param list<BlockView> $blocks
     * @param array<string, mixed> $variables
     */
    private function render(string $template, array $blocks, array $variables = []): string
    {
        $context = new RenderContext($this->prophesize(ChannelInterface::class)->reveal(), 'en_US', 'USD', null, '@guest', new \DateTimeImmutable());

        return trim($this->twig(['template' => $template])->render('template', ['blocks' => $blocks, CmsRuntime::RENDER_CONTEXT => $context] + $variables));
    }

    /**
     * @param array<string, string> $templates
     */
    private function twig(array $templates): Environment
    {
        $filesystem = new FilesystemLoader();
        $filesystem->addPath(dirname(__DIR__, 3) . '/src/Resources/views', 'SetonoSyliusCMSPlugin');

        $twig = new Environment(new ChainLoader([new ArrayLoader($templates), $filesystem]), ['strict_variables' => true]);
        $twig->addExtension(new CmsExtension());

        $renderer = new TwigBlockRenderer($twig, new BlockTypeRegistry(['heading' => Heading::class], new FieldReader()));
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([CmsRuntime::class => static fn (): CmsRuntime => new CmsRuntime($renderer)]));

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
