<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Validation;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Localization\Availability;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Routing\TakenUrl;
use Setono\SyliusCMSPlugin\Routing\TakenUrlFinderInterface;
use Setono\SyliusCMSPlugin\Routing\UrlClaim;
use Setono\SyliusCMSPlugin\Routing\UrlClash;
use Setono\SyliusCMSPlugin\Routing\UrlClashFinderInterface;
use Setono\SyliusCMSPlugin\Tests\Support\ChannelsAndAudience;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;
use Setono\SyliusCMSPlugin\Validation\EntryUrlValidator;
use Setono\SyliusCMSPlugin\Validation\ValidationError;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\Channel;

final class EntryUrlValidatorTest extends TestCase
{
    use ProphecyTrait;

    private const OTHER = '0193a5be-0000-7000-8000-000000000001';

    /** @var ObjectProphecy<UrlClashFinderInterface> */
    private ObjectProphecy $clashes;

    /** @var ObjectProphecy<TakenUrlFinderInterface> */
    private ObjectProphecy $takenUrls;

    /** @var ObjectProphecy<ChannelRepositoryInterface<ChannelInterface>> */
    private ObjectProphecy $channels;

    private Page $page;

    protected function setUp(): void
    {
        $this->clashes = $this->prophesize(UrlClashFinderInterface::class);
        $this->clashes->findPublished(Argument::cetera())->willReturn([]);
        $this->clashes->findDrafted(Argument::cetera())->willReturn([]);
        $this->takenUrls = $this->prophesize(TakenUrlFinderInterface::class);
        $this->takenUrls->find(Argument::cetera())->willReturn([]);
        /** @var ObjectProphecy<ChannelRepositoryInterface<ChannelInterface>> $channels */
        $channels = $this->prophesize(ChannelRepositoryInterface::class);
        $this->channels = $channels;
        $this->channels->findAll()->willReturn([self::channel('WEB', 'Web store'), self::channel('B2B', 'Wholesale'), self::channel('MOBILE', null)]);

        $this->page = self::page(['en_US' => 'about-us', 'da_DK' => 'om-os'], ['WEB', 'B2B']);
    }

    /**
     * @test
     */
    public function it_finds_nothing_wrong_with_valid_free_urls(): void
    {
        self::assertSame([], $this->validator()->validate($this->page));

        $this->takenUrls->find($this->page, ['da_DK' => 'om-os', 'en_US' => 'about-us'], ['B2B', 'WEB'])->shouldHaveBeenCalledOnce();
        $this->clashes->findPublished($this->page, ['da_DK' => 'om-os', 'en_US' => 'about-us'], ['B2B', 'WEB'])->shouldHaveBeenCalledOnce();
    }

    /**
     * @test
     */
    public function it_refuses_an_empty_slug_and_one_that_breaks_the_rules_and_checks_only_the_others(): void
    {
        $page = self::page(['en_US' => '/about', 'da_DK' => null, 'de_DE' => 'uber-uns'], ['WEB']);

        self::assertEquals([
            new ValidationError(null, 'slug', 'da_DK', 'A URL needs a slug.'),
            new ValidationError(null, 'slug', 'en_US', 'A slug can\'t start or end with a slash, or contain two slashes in a row.'),
        ], $this->validator()->validate($page));

        $this->takenUrls->find($page, ['de_DE' => 'uber-uns'], ['WEB'])->shouldHaveBeenCalledOnce();
        $this->clashes->findPublished($page, ['de_DE' => 'uber-uns'], ['WEB'])->shouldHaveBeenCalledOnce();
    }

    /**
     * A translation without a title is no translation, such as one whose adding was undone in the builder, so it has no
     * URL to check
     *
     * @test
     */
    public function it_checks_no_url_of_a_translation_without_a_title(): void
    {
        $page = self::page(['en_US' => 'about-us', 'da_DK' => null, 'de_DE' => 'om-os'], ['WEB']);
        foreach (['da_DK', 'de_DE'] as $localeCode) {
            $page->getTranslation($localeCode)->setTitle(null);
        }

        self::assertSame([], $this->validator()->validate($page));
        self::assertSame([], $this->validator()->warn($page));
        $this->takenUrls->find($page, ['en_US' => 'about-us'], ['WEB'])->shouldHaveBeenCalledOnce();
        $this->clashes->findPublished($page, ['en_US' => 'about-us'], ['WEB'])->shouldHaveBeenCalledOnce();
        $this->clashes->findDrafted($page, ['en_US' => 'about-us'], ['WEB'])->shouldHaveBeenCalledOnce();
    }

    /**
     * @test
     */
    public function it_refuses_a_url_another_route_takes_once_per_locale_after_the_slug_rules(): void
    {
        $page = self::page(['en_US' => 'cart', 'da_DK' => 'kurv', 'de_DE' => ''], ['WEB', 'B2B']);
        $this->takenUrls->find($page, Argument::cetera())->willReturn([
            new TakenUrl('B2B', 'en_US', 'cart', 'sylius_shop_cart_summary'),
            new TakenUrl('WEB', 'en_US', 'cart', 'app_cart'),
            new TakenUrl('WEB', 'da_DK', 'kurv', 'app_kurv'),
        ]);

        self::assertEquals([
            new ValidationError(null, 'slug', 'de_DE', 'A URL needs a slug.'),
            new ValidationError(null, 'slug', 'en_US', 'The URL /en_US/cart is taken by the route sylius_shop_cart_summary.'),
            new ValidationError(null, 'slug', 'da_DK', 'The URL /da_DK/kurv is taken by the route app_kurv.'),
        ], $this->validator()->validate($page));
    }

    /**
     * @test
     */
    public function it_refuses_another_entrys_published_url_naming_the_entry_and_the_channels_it_has_it_in(): void
    {
        $this->takenUrls->find(Argument::cetera())->willReturn([new TakenUrl('WEB', 'en_US', 'about-us', 'app_about')]);
        $this->clashes->findPublished($this->page, Argument::cetera())->willReturn([
            new UrlClash('en_US', 'about-us', self::claim('About', 'en_US', 'about-us'), ['WEB', 'B2B', 'MOBILE', 'GONE']),
            new UrlClash('da_DK', 'om-os', self::claim(null, 'da_DK', 'om-os'), ['B2B']),
        ]);

        self::assertEquals([
            new ValidationError(null, 'slug', 'en_US', 'The URL /en_US/about-us is taken by the route app_about.'),
            new ValidationError(null, 'slug', 'en_US', 'The URL /en_US/about-us is already the URL of "About" in Web store, Wholesale, MOBILE, GONE.'),
            new ValidationError(null, 'slug', 'da_DK', 'The URL /da_DK/om-os is already the URL of "' . self::OTHER . '" in Wholesale.'),
        ], $this->validator()->validate($this->page));
    }

    /**
     * @test
     */
    public function it_reads_the_channels_names_only_for_a_clash(): void
    {
        $this->validator()->validate($this->page);

        $this->channels->findAll()->shouldNotHaveBeenCalled();
    }

    /**
     * @test
     */
    public function it_warns_of_other_entries_drafted_urls_with_valid_slugs(): void
    {
        $page = self::page(['en_US' => 'contact', 'da_DK' => 'kontakt/', 'de_DE' => null], ['WEB']);
        $this->clashes->findDrafted($page, ['en_US' => 'contact'], ['WEB'])->willReturn([
            new UrlClash('en_US', 'contact', self::claim('Contact', 'en_US', 'contact'), ['WEB']),
        ]);

        self::assertEquals(
            [new ValidationError(null, 'slug', 'en_US', '"Contact" has the URL /en_US/contact in its draft too, in Web store. Only one of them can be published there.')],
            $this->validator()->warn($page),
        );
    }

    /**
     * @test
     */
    public function it_warns_of_a_live_url_another_route_takes_now(): void
    {
        $this->takenUrls->findLive($this->page)->willReturn([new TakenUrl('WEB', 'en_US', 'about-us', 'app_about')]);

        self::assertEquals(
            [new ValidationError(null, 'slug', 'en_US', 'Shoppers don\'t see this at /en_US/about-us: the route app_about takes the URL.')],
            $this->validator()->warnTakenLiveUrls($this->page),
        );
    }

    /**
     * @test
     */
    public function it_refuses_the_slugs_of_a_save_whose_url_another_route_takes(): void
    {
        $this->takenUrls->find($this->page, ['en_US' => 'cart', 'da_DK' => 'om'], ['B2B', 'WEB'])->willReturn([
            new TakenUrl('B2B', 'en_US', 'cart', 'sylius_shop_cart_summary'),
        ]);

        self::assertEquals(
            ['en_US' => new ValidationError(null, 'slug', 'en_US', 'The URL /en_US/cart is taken by the route sylius_shop_cart_summary.')],
            $this->validator()->refuse($this->page, ['en_US' => 'cart', 'de_DE' => '/cart', 'da_DK' => 'om']),
        );
    }

    /**
     * @test
     */
    public function it_refuses_channels_to_add_where_another_entry_has_published_one_of_its_published_urls(): void
    {
        $this->page->publish(new \DateTimeImmutable());
        $this->clashes->findPublished($this->page, ['da_DK' => 'om-os', 'en_US' => 'about-us'], ['MOBILE'])->willReturn([
            new UrlClash('en_US', 'about-us', self::claim('About', 'en_US', 'about-us'), ['MOBILE']),
        ]);

        self::assertEquals(
            [new ValidationError(null, 'slug', 'en_US', 'The URL /en_US/about-us is already the URL of "About" in MOBILE, so this can\'t be shown there too.')],
            $this->validator()->validateChannels($this->page, ['B2B', 'MOBILE']),
        );
    }

    /**
     * @test
     */
    public function it_lets_the_channels_of_a_never_published_entry_or_channels_it_is_already_in_change(): void
    {
        $this->clashes->findPublished(Argument::cetera())->willReturn([new UrlClash('en_US', 'about-us', self::claim('About', 'en_US', 'about-us'), ['WEB'])]);

        self::assertSame([], $this->validator()->validateChannels($this->page, ['MOBILE', 'WEB']));

        $this->page->publish(new \DateTimeImmutable());
        self::assertSame([], $this->validator()->validateChannels($this->page, ['WEB']));
        self::assertSame([], $this->validator()->validateChannels($this->page, []));
    }

    /**
     * @test
     */
    public function an_entry_without_a_url_of_its_own_has_no_url_to_check(): void
    {
        $recipe = new Recipe();
        $recipe->setCurrentLocale('en_US');
        $recipe->setTitle('Pancakes');
        $recipe->publish(new \DateTimeImmutable());
        ChannelsAndAudience::modifier()->setChannels($recipe, ['WEB']);
        $this->takenUrls->find(Argument::cetera())->willReturn([new TakenUrl('WEB', 'en_US', 'cart', 'app_cart')]);
        $this->clashes->findPublished(Argument::cetera())->willReturn([new UrlClash('en_US', 'cart', self::claim('Cart', 'en_US', 'cart'), ['MOBILE'])]);
        $this->clashes->findDrafted(Argument::cetera())->willReturn([new UrlClash('en_US', 'cart', self::claim('Cart', 'en_US', 'cart'), ['WEB'])]);

        $validator = $this->validator();
        self::assertSame([], $validator->validate($recipe));
        self::assertSame([], $validator->warn($recipe));
        self::assertSame([], $validator->warnTakenLiveUrls($recipe));
        self::assertSame([], $validator->refuse($recipe, ['en_US' => 'cart']));
        self::assertSame([], $validator->validateChannels($recipe, ['MOBILE', 'WEB']));
    }

    private function validator(): EntryUrlValidator
    {
        return new EntryUrlValidator(
            new ContentTypeRegistry([
                'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
                'recipe' => ['model' => Recipe::class, 'translation' => null, 'resource' => 'app.recipe'],
            ]),
            $this->clashes->reveal(),
            $this->takenUrls->reveal(),
            $this->channels->reveal(),
            BlockTreeValidatorTest::translator(),
            new Availability(),
        );
    }

    /**
     * @param array<string, string|null> $slugs
     * @param list<string> $channelCodes
     */
    private static function page(array $slugs, array $channelCodes): Page
    {
        $page = new Page();
        foreach ($slugs as $localeCode => $slug) {
            $translation = new PageTranslation();
            $translation->setLocale($localeCode);
            $translation->setTitle('Title');
            $translation->setSlug($slug);
            $page->addTranslation($translation);
        }
        ChannelsAndAudience::modifier()->setChannels($page, $channelCodes);

        return $page;
    }

    private static function claim(?string $title, string $localeCode, string $slug): UrlClaim
    {
        return new UrlClaim(self::OTHER, 'page', $localeCode, $slug, $title, ['WEB']);
    }

    private static function channel(string $code, ?string $name): Channel
    {
        $channel = new Channel();
        $channel->setCode($code);
        $channel->setName($name);

        return $channel;
    }
}
