<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\ShareLink;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\Placement;
use Setono\SyliusCMSPlugin\Model\ShareLink;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Preview\SampleObject;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyProviderInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Rendering\ReferenceLoaderInterface;
use Setono\SyliusCMSPlugin\Rendering\ReferenceMap;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Repository\PlacementRepositoryInterface;
use Setono\SyliusCMSPlugin\ShareLink\ShareLinkUrlGenerator;
use Setono\SyliusCMSPlugin\Tests\Support\SnippetPreviews;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;
use Sylius\Component\Channel\Model\ChannelInterface as BaseChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\Product;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * A share link's URL is the entry's URL in its channel and locale, on the channel's hostname, with its token in
 * _cms_share. See "Share links" in docs/spec/publishing.md
 */
final class ShareLinkUrlGeneratorTest extends TestCase
{
    use ProphecyTrait;

    private const TOKEN = 'k3Rq0Zl8vN2mX5pT7wY1aB4cD6eF9gH0iJ2kL5mN8oP';

    /** @var ObjectProphecy<ChannelRepositoryInterface<BaseChannelInterface>> */
    private ObjectProphecy $channels;

    private UrlGenerator $urlGenerator;

    /** @var ObjectProphecy<PlacementRepositoryInterface> */
    private ObjectProphecy $placements;

    protected function setUp(): void
    {
        /** @var ObjectProphecy<ChannelRepositoryInterface<BaseChannelInterface>> $channels */
        $channels = $this->prophesize(ChannelRepositoryInterface::class);
        $this->channels = $channels;
        $this->channels->findOneByCode(Argument::any())->willReturn(null);
        $this->channels->findOneByCode('WEB')->willReturn(self::channel('WEB', 'shop.example.com'));
        $this->channels->findOneByCode('NOHOST')->willReturn(self::channel('NOHOST', null));

        $routes = new RouteCollection();
        $routes->add('setono_sylius_cms_shop_entry', new Route('/{_locale}/{path}', requirements: ['path' => '.+(?<!/)']));
        $routes->add('sylius_shop_product_show', new Route('/{_locale}/products/{slug}'));
        $routes->add('setono_sylius_cms_shop_snippet_preview', new Route('/{_locale}/_cms/snippet-preview'));
        $this->urlGenerator = new UrlGenerator($routes, new RequestContext('', 'GET', 'admin.example.com', 'https'));

        $this->placements = $this->prophesize(PlacementRepositoryInterface::class);
    }

    /**
     * A snippet's link is on the host page of its placement: the page of its sample object, on the channel's hostname.
     * See "Share links" in docs/spec/publishing.md
     *
     * @test
     */
    public function it_is_the_host_page_of_a_snippet_s_placement_with_its_sample_object(): void
    {
        $snippet = new Snippet();
        $shareLink = self::shareLink($snippet, 'WEB', 'en_US');
        $shareLink->setPlacement(new Placement($snippet, 'product.after_price', 0));
        $shareLink->setSample(new SampleObject('product', 'MUG'));

        self::assertSame('https://shop.example.com/en_US/products/mug?_cms_share=' . self::TOKEN, $this->generator()->generate($shareLink));
        self::assertSame('admin.example.com', $this->urlGenerator->getContext()->getHost());
    }

    /**
     * @test
     */
    public function it_is_a_plain_page_for_a_snippet_without_a_placement(): void
    {
        self::assertSame(
            'https://shop.example.com/en_US/_cms/snippet-preview?_cms_share=' . self::TOKEN,
            $this->generator()->generate(self::shareLink(new Snippet(), 'WEB', 'en_US')),
        );
    }

    /**
     * @test
     */
    public function it_is_the_entrys_url_in_the_channel_and_locale_on_the_channels_hostname_with_the_token(): void
    {
        $shareLink = self::shareLink(self::page(['en_US' => 'about-us', 'da_DK' => 'om-os/team']), 'WEB', 'da_DK');

        self::assertSame('https://shop.example.com/da_DK/om-os/team?_cms_share=' . self::TOKEN, $this->generator()->generate($shareLink));
    }

    /**
     * @test
     */
    public function it_puts_the_routers_own_host_back_afterwards(): void
    {
        $this->generator()->generate(self::shareLink(self::page(['en_US' => 'about-us']), 'WEB', 'en_US'));

        self::assertSame('admin.example.com', $this->urlGenerator->getContext()->getHost());
        self::assertSame('https://admin.example.com/en_US/about-us', $this->urlGenerator->generate('setono_sylius_cms_shop_entry', ['_locale' => 'en_US', 'path' => 'about-us'], UrlGenerator::ABSOLUTE_URL));
    }

    /**
     * @test
     */
    public function it_stays_on_the_current_host_for_a_channel_without_a_hostname_or_one_that_is_gone(): void
    {
        $page = self::page(['en_US' => 'about-us']);

        self::assertSame('https://admin.example.com/en_US/about-us?_cms_share=' . self::TOKEN, $this->generator()->generate(self::shareLink($page, 'NOHOST', 'en_US')));
        self::assertSame('https://admin.example.com/en_US/about-us?_cms_share=' . self::TOKEN, $this->generator()->generate(self::shareLink($page, 'GONE', 'en_US')));
    }

    /**
     * The link shows the draft, at the draft's URL
     *
     * @test
     */
    public function it_is_at_the_url_of_the_entrys_working_copy(): void
    {
        $page = self::page(['en_US' => 'about-us']);
        $workingCopy = clone $page;
        $workingCopy->setCurrentLocale('en_US');
        $workingCopy->setSlug('about');

        self::assertSame('https://shop.example.com/en_US/about?_cms_share=' . self::TOKEN, $this->generator($page, $workingCopy)->generate(self::shareLink($page, 'WEB', 'en_US')));
    }

    /**
     * @test
     */
    public function it_is_null_once_the_entry_has_no_url_in_the_locale(): void
    {
        self::assertNull($this->generator()->generate(self::shareLink(self::page(['en_US' => 'about-us']), 'WEB', 'da_DK')));

        $recipe = new Recipe();
        $recipe->setCurrentLocale('en_US');
        $recipe->setTitle('Pancakes');
        self::assertNull($this->generator()->generate(self::shareLink($recipe, 'WEB', 'en_US')));
    }

    /**
     * @param EntryInterface|null $entry an entry whose working copy is $workingCopy, while every other entry is its own
     */
    private function generator(?EntryInterface $entry = null, ?EntryInterface $workingCopy = null): ShareLinkUrlGenerator
    {
        $workingCopies = $this->prophesize(WorkingCopyProviderInterface::class);
        $workingCopies->getWorkingCopy(Argument::type(EntryInterface::class))->willReturnArgument(0);
        if (null !== $entry && null !== $workingCopy) {
            $workingCopies->getWorkingCopy($entry)->willReturn($workingCopy);
        }

        return new ShareLinkUrlGenerator(
            $workingCopies->reveal(),
            new ContentTypeRegistry([
                'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
                'recipe' => ['model' => Recipe::class, 'translation' => null, 'resource' => 'app.recipe'],
            ]),
            $this->channels->reveal(),
            $this->urlGenerator,
            SnippetPreviews::hostPages($this->placements->reveal(), urlGenerator: $this->urlGenerator),
            SnippetPreviews::samples(self::mug()),
            SnippetPreviews::renderContexts(),
        );
    }

    /**
     * Loads the product MUG, whose slug is mug
     */
    private static function mug(): ReferenceLoaderInterface
    {
        $mug = new Product();
        $mug->setCode('MUG');
        $mug->setCurrentLocale('en_US');
        $mug->setSlug('mug');

        return new class($mug) implements ReferenceLoaderInterface {
            public function __construct(private readonly Product $mug)
            {
            }

            public function load(iterable $references, RenderContext $context): ReferenceMap
            {
                return new ReferenceMap(['product' => ['MUG' => $this->mug]]);
            }
        };
    }

    private static function shareLink(EntryInterface $entry, string $channelCode, string $localeCode): ShareLink
    {
        $shareLink = new ShareLink();
        $shareLink->setEntry($entry);
        $shareLink->setToken(self::TOKEN);
        $shareLink->setChannelCode($channelCode);
        $shareLink->setLocaleCode($localeCode);

        return $shareLink;
    }

    /**
     * @param array<string, string> $slugs
     */
    private static function page(array $slugs): Page
    {
        $page = new Page();
        foreach ($slugs as $localeCode => $slug) {
            $page->setCurrentLocale($localeCode);
            $page->setTitle('About us');
            $page->setSlug($slug);
        }

        return $page;
    }

    private static function channel(string $code, ?string $hostname): Channel
    {
        $channel = new Channel();
        $channel->setCode($code);
        $channel->setHostname($hostname);

        return $channel;
    }
}
