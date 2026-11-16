<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Routing;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Localization\Availability;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Routing\RouteCheckerInterface;
use Setono\SyliusCMSPlugin\Routing\TakenUrl;
use Setono\SyliusCMSPlugin\Routing\TakenUrlFinder;
use Setono\SyliusCMSPlugin\Tests\Support\ChannelsAndAudience;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;
use Setono\SyliusCMSPlugin\Tests\Unit\Upgrade\Fixture\Article;
use Sylius\Component\Channel\Model\Channel as PlainChannel;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Locale\Model\Locale;

final class TakenUrlFinderTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<RouteCheckerInterface> */
    private ObjectProphecy $routeChecker;

    /** @var ObjectProphecy<ChannelRepositoryInterface<ChannelInterface>> */
    private ObjectProphecy $channelRepository;

    /** @var list<array{string|null, string, string}> the channel, locale and path of each check */
    private array $checked = [];

    protected function setUp(): void
    {
        $checked = &$this->checked;
        $this->routeChecker = $this->prophesize(RouteCheckerInterface::class);
        $this->routeChecker->takenBy(Argument::cetera())->will(static function (array $arguments) use (&$checked): ?string {
            /** @var array{ChannelInterface|null, string, string} $arguments */
            [$channel, $localeCode, $path] = $arguments;
            $checked[] = [$channel?->getCode(), $localeCode, $path];

            return match ($path) {
                'cart', 'articles/cart' => 'sylius_shop_cart_summary',
                'login' => 'sylius_shop_login',
                default => null,
            };
        });

        /** @var ObjectProphecy<ChannelRepositoryInterface<ChannelInterface>> $channelRepository */
        $channelRepository = $this->prophesize(ChannelRepositoryInterface::class);
        $this->channelRepository = $channelRepository;
        // A channel that isn't a shop's has no hostname or locales to check in
        $plain = new PlainChannel();
        $plain->setCode('PLAIN');
        $this->channelRepository->findAll()->willReturn([self::channel('WEB', 'en_US', 'da_DK'), self::channel('B2B', 'en_US'), self::channel(null), $plain]);
    }

    /**
     * @test
     */
    public function it_finds_the_urls_another_route_takes_in_each_of_the_entrys_channels(): void
    {
        $taken = $this->finder()->find(new Page(), ['en_US' => 'cart', 'da_DK' => 'om-os', 'de_DE' => 'login'], ['WEB', 'B2B']);

        self::assertEquals([
            new TakenUrl('B2B', 'en_US', 'cart', 'sylius_shop_cart_summary'),
            new TakenUrl('B2B', 'de_DE', 'login', 'sylius_shop_login'),
            new TakenUrl('WEB', 'en_US', 'cart', 'sylius_shop_cart_summary'),
            new TakenUrl('WEB', 'de_DE', 'login', 'sylius_shop_login'),
        ], $taken);
        self::assertCount(6, $this->checked);
    }

    /**
     * @test
     */
    public function it_checks_the_url_with_the_content_types_prefix(): void
    {
        $taken = $this->finder()->find(new Article(), ['en_US' => 'cart'], ['B2B']);

        self::assertEquals([new TakenUrl('B2B', 'en_US', 'articles/cart', 'sylius_shop_cart_summary')], $taken);
    }

    /**
     * @test
     */
    public function it_finds_the_live_urls_another_route_takes_in_the_locales_the_entry_shows_in_where_its_channel_has_the_locale(): void
    {
        $page = self::page(['WEB', 'B2B'], [
            'da_DK' => ['Log ind', 'login'],
            'en_US' => ['Cart', 'cart'],
            // Neither channel has German
            'de_DE' => ['Anmelden', 'login'],
            'fr_FR' => ['Panier', 'cart', false],
            // No title, so no translation
            'nl_NL' => [null, 'cart'],
            'sv_SE' => ['Kundvagn', '/cart'],
        ]);

        self::assertEquals([
            new TakenUrl('B2B', 'en_US', 'cart', 'sylius_shop_cart_summary'),
            new TakenUrl('WEB', 'da_DK', 'login', 'sylius_shop_login'),
            new TakenUrl('WEB', 'en_US', 'cart', 'sylius_shop_cart_summary'),
        ], $this->finder()->findLive($page));
        self::assertSame([['B2B', 'en_US', 'cart'], ['WEB', 'da_DK', 'login'], ['WEB', 'en_US', 'cart']], $this->checked);
    }

    /**
     * @test
     */
    public function it_finds_no_live_url_of_a_never_published_entry_or_one_in_no_channel(): void
    {
        self::assertSame([], $this->finder()->findLive(self::page(['WEB'], ['en_US' => ['Cart', 'cart']], false)));
        self::assertSame([], $this->finder()->findLive(self::page([], ['en_US' => ['Cart', 'cart']])));
        self::assertSame([], $this->checked);
    }

    /**
     * @test
     */
    public function it_checks_once_on_the_current_host_for_an_entry_in_no_channel(): void
    {
        $taken = $this->finder()->find(new Page(), ['en_US' => 'cart', 'da_DK' => 'login'], []);

        self::assertEquals([
            new TakenUrl(null, 'en_US', 'cart', 'sylius_shop_cart_summary'),
            new TakenUrl(null, 'da_DK', 'login', 'sylius_shop_login'),
        ], $taken);
    }

    /**
     * @test
     */
    public function it_skips_a_channel_the_store_no_longer_has_or_that_is_not_a_shops(): void
    {
        self::assertSame([], $this->finder()->find(new Page(), ['en_US' => 'cart'], ['GONE', 'PLAIN']));
        self::assertSame([], $this->checked);
    }

    /**
     * @test
     */
    public function an_entry_without_a_url_of_its_own_or_a_slug_has_no_url_to_take(): void
    {
        self::assertSame([], $this->finder()->find(new Recipe(), ['en_US' => 'cart'], ['WEB']));
        self::assertEquals(
            [new TakenUrl('WEB', 'en_US', 'cart', 'sylius_shop_cart_summary')],
            $this->finder()->find(new Page(), ['da_DK' => '', 'en_US' => 'cart'], ['WEB']),
        );
        self::assertSame([['WEB', 'en_US', 'cart']], $this->checked);
    }

    /**
     * @test
     */
    public function it_reads_the_stores_channels_once_until_it_is_reset(): void
    {
        $finder = $this->finder();
        $finder->find(new Page(), ['en_US' => 'cart'], ['WEB']);
        $finder->find(new Page(), ['en_US' => 'cart'], ['B2B']);
        $this->channelRepository->findAll()->shouldHaveBeenCalledOnce();

        $finder->reset();
        $finder->find(new Page(), ['en_US' => 'cart'], ['WEB']);
        $this->channelRepository->findAll()->shouldHaveBeenCalledTimes(2);
    }

    private function finder(): TakenUrlFinder
    {
        return new TakenUrlFinder(new ContentTypeRegistry([
            'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
            'article' => ['model' => Article::class, 'translation' => null, 'resource' => 'app.article'],
            'recipe' => ['model' => Recipe::class, 'translation' => null, 'resource' => 'app.recipe'],
        ]), $this->routeChecker->reveal(), $this->channelRepository->reveal(), new Availability());
    }

    /**
     * @param list<string> $channelCodes
     * @param array<string, array{0: string|null, 1: string, 2?: bool}> $translations locale => title, slug and whether it's shown
     */
    private static function page(array $channelCodes, array $translations, bool $published = true): Page
    {
        $page = new Page();
        foreach ($translations as $localeCode => $translation) {
            $pageTranslation = new PageTranslation();
            $pageTranslation->setLocale($localeCode);
            $pageTranslation->setTitle($translation[0]);
            $pageTranslation->setSlug($translation[1]);
            $pageTranslation->setShown($translation[2] ?? true);
            $page->addTranslation($pageTranslation);
        }
        ChannelsAndAudience::modifier()->setChannels($page, $channelCodes);

        if ($published) {
            $page->publish(new \DateTimeImmutable());
        }

        return $page;
    }

    private static function channel(?string $code, string ...$localeCodes): Channel
    {
        $channel = new Channel();
        $channel->setCode($code);
        foreach ($localeCodes as $localeCode) {
            $locale = new Locale();
            $locale->setCode($localeCode);
            $channel->addLocale($locale);
        }

        return $channel;
    }
}
