<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Twig;

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
use Setono\SyliusCMSPlugin\Tests\Unit\Twig\Fixture\NotABlock;
use Setono\SyliusCMSPlugin\Tests\Unit\Twig\Fixture\Quote;
use Setono\SyliusCMSPlugin\Twig\CMSExtension;
use Setono\SyliusCMSPlugin\Twig\CMSRuntime;
use Setono\SyliusCMSPlugin\Value\RichText as RichTextValue;
use Sylius\Component\Core\Model\Channel;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;
use Twig\Test\IntegrationTestCase;

/**
 * Runs the .test files in Fixture, one directory per function. Twig evaluates their --DATA-- sections inside this
 * test case, so they build the template variables with the public helpers below, as in
 * return $this->rendering(['blocks' => [$this->heading('Hello')]])
 */
final class CMSExtensionTest extends IntegrationTestCase
{
    public const KEY = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f';

    public const FAQ_KEY = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e50';

    /**
     * The templates of the fixture block types, beside the plugin's own block templates
     */
    private const BLOCK_TEMPLATES = [
        'quote.html.twig' => '<blockquote class="{{ cms_field(block, "size") }}"><p>{{ cms_field(block, "quote") }}</p><cite>{{ block.author }}</cite></blockquote>',
        'faq.html.twig' => '<dl>{% for child in children %}<dt>{{ cms_field(child.block, "text") }}</dt>{% endfor %}</dl>',
    ];

    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $this->requestStack = new RequestStack();
    }

    protected static function getFixturesDirectory(): string
    {
        return __DIR__ . '/Fixture';
    }

    /**
     * Twig before 3.13, such as the lowest 3.7 the plugin allows, reads the fixtures from here
     */
    protected function getFixturesDir(): string
    {
        return self::getFixturesDirectory();
    }

    /**
     * @return list<CMSExtension>
     */
    protected function getExtensions(): array
    {
        return [new CMSExtension()];
    }

    /**
     * The block renderer renders block templates with its own environment, because the one a test case gets only
     * loads the templates in its .test file
     *
     * @return list<FactoryRuntimeLoader>
     */
    protected function getRuntimeLoaders(): array
    {
        $filesystem = new FilesystemLoader();
        $filesystem->addPath(dirname(__DIR__, 3) . '/src/Resources/views', 'SetonoSyliusCMSPlugin');
        $blockTwig = new Environment(new ChainLoader([new ArrayLoader(self::BLOCK_TEMPLATES), $filesystem]), ['strict_variables' => true]);
        $blockTwig->addExtension(new CMSExtension());

        $blockTypes = new BlockTypeRegistry(['heading' => Heading::class, 'rich_text' => RichText::class, 'quote' => Quote::class, 'faq' => Faq::class], new FieldReader());
        $runtimes = new FactoryRuntimeLoader([
            CMSRuntime::class => static fn (): CMSRuntime => new CMSRuntime(new TwigBlockRenderer($blockTwig, $blockTypes), new FieldMarker($blockTypes)),
            PreviewContext::class => fn (): PreviewContext => new PreviewContext($this->requestStack, self::signer()),
        ]);
        $blockTwig->addRuntimeLoader($runtimes);

        return [$runtimes];
    }

    /**
     * The variables of a layout or block template: the given ones and the render context
     *
     * @param array<string, mixed> $variables
     * @param RenderContext::PREVIEW_MODE_*|null $previewMode
     *
     * @return array<string, mixed>
     */
    public function rendering(array $variables, ?string $previewMode = null): array
    {
        return $variables + [
            CMSRuntime::RENDER_CONTEXT => new RenderContext(new Channel(), 'en_US', 'USD', null, '@guest', new \DateTimeImmutable(), [], $previewMode),
        ];
    }

    /**
     * Makes the request one the builder's preview shows, and gives no variables
     *
     * @return array{}
     */
    public function inTheBuildersPreview(): array
    {
        $this->requestStack->push(new Request([PreviewContext::QUERY_PARAMETER => self::signer()->sign(new Preview('0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e', 'WEB', 'en_US'))]));

        return [];
    }

    /**
     * Makes the request a shopper's, and gives no variables
     *
     * @return array{}
     */
    public function forAShopper(): array
    {
        $this->requestStack->push(new Request());

        return [];
    }

    public function heading(?string $text, string $level = 'h2'): BlockView
    {
        $heading = new Heading();
        $heading->text = $text;
        $heading->level = $level;

        return new BlockView(self::KEY, 'heading', $heading, null, []);
    }

    /**
     * A rich text block with this HTML, or an empty one
     */
    public function richText(?string $html): BlockView
    {
        $richText = new RichText();
        if (null !== $html) {
            $richText->content = new RichTextValue(['type' => 'doc', 'content' => []], $html);
        }

        return new BlockView(self::KEY, 'rich_text', $richText, null, []);
    }

    public function quote(string $quote, string $author): BlockView
    {
        $block = new Quote();
        $block->quote = $quote;
        $block->author = $author;

        return new BlockView(self::KEY, 'quote', $block, null, []);
    }

    public function faq(BlockView ...$children): BlockView
    {
        return new BlockView(self::FAQ_KEY, 'faq', new Faq(), null, array_values($children));
    }

    public function notABlock(): NotABlock
    {
        return new NotABlock();
    }

    private static function signer(): PreviewTokenSigner
    {
        return new PreviewTokenSigner('secret', new MockClock());
    }
}
