<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Twig;

use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Block\RichText;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Localization\FallbackChain;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Preview\FieldMarker;
use Setono\SyliusCMSPlugin\Preview\Preview;
use Setono\SyliusCMSPlugin\Preview\PreviewContext;
use Setono\SyliusCMSPlugin\Preview\PreviewTokenSigner;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Rendering\BlockView;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Rendering\TwigBlockRenderer;
use Setono\SyliusCMSPlugin\Repository\ShareLinkRepositoryInterface;
use Setono\SyliusCMSPlugin\Seo\CanonicalUrlGenerator;
use Setono\SyliusCMSPlugin\Seo\MetaValues;
use Setono\SyliusCMSPlugin\Tests\Unit\Twig\Fixture\Faq;
use Setono\SyliusCMSPlugin\Tests\Unit\Twig\Fixture\NotABlock;
use Setono\SyliusCMSPlugin\Tests\Unit\Twig\Fixture\Quote;
use Setono\SyliusCMSPlugin\Twig\CMSExtension;
use Setono\SyliusCMSPlugin\Twig\CMSRuntime;
use Setono\SyliusCMSPlugin\Twig\HeadTagsRuntime;
use Setono\SyliusCMSPlugin\Value\RichText as RichTextValue;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Locale\Model\Locale;
use Sylius\Resource\Translation\Provider\ImmutableTranslationLocaleProvider;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
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
    use ProphecyTrait;

    public const KEY = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f';

    public const FAQ_KEY = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e50';

    /**
     * The templates of the fixture block types, beside the plugin's own block templates
     */
    private const SHARE_TOKEN = 'k3Rq0Zl8vN2mX5pT7wY1aB4cD6eF9gH0iJ2kL5mN8oP';

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
        // de_CH falls back to de_DE, and then to en_US
        $fallbackValues = new FallbackValues(new FieldHandlerLocator(new ServiceLocator([])), new FallbackChain(new ImmutableTranslationLocaleProvider(['en_US'], 'en_US'), ['de_CH' => ['de_DE']]));
        $contentTypes = new ContentTypeRegistry(['page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page']]);
        $shopRoutes = new RouteCollection();
        $shopRoutes->add('setono_sylius_cms_shop_entry', new Route('/{_locale}/{path}', requirements: ['path' => '.+(?<!/)']));
        $runtimes = new FactoryRuntimeLoader([
            CMSRuntime::class => static fn (): CMSRuntime => new CMSRuntime(
                new TwigBlockRenderer($blockTwig, $blockTypes),
                new FieldMarker($blockTypes),
                $fallbackValues,
            ),
            PreviewContext::class => fn (): PreviewContext => new PreviewContext($this->requestStack, self::signer(), $this->shareLinks(...)),
            HeadTagsRuntime::class => static fn (): HeadTagsRuntime => new HeadTagsRuntime(
                new MetaValues($contentTypes, $fallbackValues),
                new CanonicalUrlGenerator($contentTypes, new UrlGenerator($shopRoutes, new RequestContext(host: 'shop.example', scheme: 'https'))),
            ),
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
    public function rendering(array $variables, ?string $previewMode = null, string $locale = 'en_US', ?ChannelInterface $channel = null): array
    {
        return $variables + [
            CMSRuntime::RENDER_CONTEXT => new RenderContext($channel ?? new Channel(), $locale, 'USD', null, '@guest', new \DateTimeImmutable(), [], $previewMode),
        ];
    }

    /**
     * A channel with these locales, and its default locale, when it has one
     *
     * @param list<string> $locales
     */
    public function channel(array $locales, ?string $defaultLocale = null): ChannelInterface
    {
        $channel = new Channel();
        foreach ($locales as $code) {
            $locale = new Locale();
            $locale->setCode($code);
            $channel->addLocale($locale);
            if ($code === $defaultLocale) {
                $channel->setDefaultLocale($locale);
            }
        }

        return $channel;
    }

    /**
     * A page with these translations, each with a title, a slug, SEO fields and its shown switch as given
     *
     * @param array<string, array{title?: string|null, slug?: string|null, metaTitle?: string|null, metaDescription?: string|null, shown?: bool}> $translations by locale
     */
    public function pageWith(array $translations, bool $noindex = false): Page
    {
        $page = new Page();
        $page->setNoindex($noindex);
        foreach ($translations as $locale => $fields) {
            $translation = new PageTranslation();
            $translation->setLocale($locale);
            $translation->setTitle($fields['title'] ?? null);
            $translation->setSlug($fields['slug'] ?? null);
            $translation->setMetaTitle($fields['metaTitle'] ?? null);
            $translation->setMetaDescription($fields['metaDescription'] ?? null);
            $translation->setShown($fields['shown'] ?? true);
            $page->addTranslation($translation);
        }

        return $page;
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
     * Makes the request one a share link shows, and gives no variables
     *
     * @return array{}
     */
    public function onAShareLink(): array
    {
        $this->requestStack->push(new Request([PreviewContext::SHARE_QUERY_PARAMETER => self::SHARE_TOKEN]));

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

    /**
     * The share links there are: one, whose token is SHARE_TOKEN
     */
    private function shareLinks(): ShareLinkRepositoryInterface
    {
        $shareLinks = $this->prophesize(ShareLinkRepositoryInterface::class);
        $shareLinks->lookUpToken(Argument::any())->willReturn(null);
        $shareLinks->lookUpToken(self::SHARE_TOKEN)->willReturn(['entry' => '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e', 'channel' => 'WEB', 'locale' => 'en_US', 'shownTo' => '@guest', 'date' => null]);

        return $shareLinks->reveal();
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

    /**
     * A page with these titles
     *
     * @param array<string, string> $titles by locale
     */
    public function page(array $titles): Page
    {
        $page = new Page();
        foreach ($titles as $locale => $title) {
            $translation = new PageTranslation();
            $translation->setLocale($locale);
            $translation->setTitle($title);
            $page->addTranslation($translation);
        }

        return $page;
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
