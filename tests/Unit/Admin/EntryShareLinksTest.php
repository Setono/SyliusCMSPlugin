<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Admin\EntryShareLink;
use Setono\SyliusCMSPlugin\Admin\EntryShareLinks;
use Setono\SyliusCMSPlugin\Admin\ShareLinkTarget;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\ShareLink;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyProviderInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\SlotRegistry;
use Setono\SyliusCMSPlugin\Repository\PlacementRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\ShareLinkRepositoryInterface;
use Setono\SyliusCMSPlugin\ShareLink\ShareLinkUrlGenerator;
use Setono\SyliusCMSPlugin\Tests\Support\ChannelsAndAudience;
use Setono\SyliusCMSPlugin\Tests\Support\SnippetPreviews;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;
use Sylius\Component\Channel\Model\ChannelInterface as BaseChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Locale\Model\Locale;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * What the Share links section of an entry's overview page lists, and where its Share draft offers to share the draft.
 * See "Entry overview page" in docs/spec/admin.md
 */
final class EntryShareLinksTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ShareLinkRepositoryInterface> */
    private ObjectProphecy $shareLinks;

    /** @var ObjectProphecy<ChannelRepositoryInterface<BaseChannelInterface>> */
    private ObjectProphecy $channels;

    protected function setUp(): void
    {
        $this->shareLinks = $this->prophesize(ShareLinkRepositoryInterface::class);
        $this->shareLinks->findByEntry(Argument::any())->willReturn([]);

        /** @var ObjectProphecy<ChannelRepositoryInterface<BaseChannelInterface>> $channels */
        $channels = $this->prophesize(ChannelRepositoryInterface::class);
        $this->channels = $channels;
        $this->channels->findOneByCode(Argument::any())->willReturn(null);
        $this->channels->findOneByCode('WEB')->willReturn(self::channel('WEB', 'Web store', 'shop.example.com', 'en_US', 'fr_FR', 'da_DK'));
        $this->channels->findOneByCode('B2B')->willReturn(self::channel('B2B', 'Wholesale', null, 'de_DE', 'en_US'));
        $this->channels->findOneByCode('UNNAMED')->willReturn(self::channel('UNNAMED', '', null, 'en_US'));
        $this->channels->findOneByCode('NODEFAULT')->willReturn(self::channel('NODEFAULT', 'No default', null, null, 'fr_FR', 'en_US'));
    }

    /**
     * @test
     */
    public function it_lists_the_entrys_share_links_as_the_repository_orders_them_with_their_url_and_channel_name(): void
    {
        $page = self::page(['en_US' => 'about-us', 'da_DK' => 'om-os'], ['WEB']);
        $danish = self::shareLink($page, 'WEB', 'da_DK', 'tokenDanish');
        $english = self::shareLink($page, 'WEB', 'en_US', 'tokenEnglish');
        $this->shareLinks->findByEntry($page)->willReturn([$danish, $english]);

        self::assertEquals([
            new EntryShareLink($danish, 'https://shop.example.com/da_DK/om-os?_cms_share=tokenDanish', 'Web store'),
            new EntryShareLink($english, 'https://shop.example.com/en_US/about-us?_cms_share=tokenEnglish', 'Web store'),
        ], $this->entryShareLinks()->of($page));
    }

    /**
     * @test
     */
    public function it_names_a_channel_without_a_name_or_one_that_is_gone_by_its_code(): void
    {
        $page = self::page(['en_US' => 'about-us'], ['UNNAMED']);
        $unnamed = self::shareLink($page, 'UNNAMED', 'en_US', 'tokenUnnamed');
        $gone = self::shareLink($page, 'GONE', 'en_US', 'tokenGone');
        $this->shareLinks->findByEntry($page)->willReturn([$unnamed, $gone]);

        $shareLinks = $this->entryShareLinks()->of($page);

        self::assertSame(['UNNAMED', 'GONE'], array_map(static fn (EntryShareLink $shareLink): string => $shareLink->channelName, $shareLinks));
    }

    /**
     * @test
     */
    public function it_lists_no_url_for_a_share_link_whose_locale_the_draft_has_no_url_in_anymore(): void
    {
        $page = self::page(['en_US' => 'about-us'], ['WEB']);
        $this->shareLinks->findByEntry($page)->willReturn([self::shareLink($page, 'WEB', 'da_DK', 'tokenDanish')]);

        self::assertNull($this->entryShareLinks()->of($page)[0]->url);
    }

    /**
     * Its channels by code, each with its default locale first and then its other locales by code
     *
     * @test
     */
    public function it_offers_to_share_the_draft_in_each_channel_and_locale_it_has_a_url_in(): void
    {
        $page = self::page(['en_US' => 'about-us', 'da_DK' => 'om-os', 'de_DE' => 'uber-uns', 'fr_FR' => 'a-propos'], ['WEB', 'B2B', 'NODEFAULT']);

        self::assertEquals([
            new ShareLinkTarget('B2B', 'Wholesale', 'de_DE'),
            new ShareLinkTarget('B2B', 'Wholesale', 'en_US'),
            new ShareLinkTarget('NODEFAULT', 'No default', 'en_US'),
            new ShareLinkTarget('NODEFAULT', 'No default', 'fr_FR'),
            new ShareLinkTarget('WEB', 'Web store', 'en_US'),
            new ShareLinkTarget('WEB', 'Web store', 'da_DK'),
            new ShareLinkTarget('WEB', 'Web store', 'fr_FR'),
        ], $this->entryShareLinks()->targets($page));
    }

    /**
     * @test
     */
    public function it_offers_no_locale_the_draft_has_no_url_in_and_no_channel_that_is_gone(): void
    {
        $page = self::page(['en_US' => 'about-us'], ['WEB', 'GONE', 'UNNAMED']);
        // An unfinished locale, without a code yet
        $web = $this->channels->reveal()->findOneByCode('WEB');
        self::assertInstanceOf(Channel::class, $web);
        $web->addLocale(new Locale());

        self::assertEquals([
            new ShareLinkTarget('UNNAMED', 'UNNAMED', 'en_US'),
            new ShareLinkTarget('WEB', 'Web store', 'en_US'),
        ], $this->entryShareLinks()->targets($page));
    }

    /**
     * The link opens the draft, at the draft's URL
     *
     * @test
     */
    public function it_offers_the_locales_the_working_copy_has_a_url_in(): void
    {
        $page = self::page(['en_US' => 'about-us'], ['WEB']);
        $workingCopy = clone $page;
        $workingCopy->setCurrentLocale('da_DK');
        $workingCopy->setTitle('Om os');
        $workingCopy->setSlug('om-os');

        self::assertEquals([
            new ShareLinkTarget('WEB', 'Web store', 'en_US'),
            new ShareLinkTarget('WEB', 'Web store', 'da_DK'),
        ], $this->entryShareLinks($page, $workingCopy)->targets($page));
    }

    /**
     * @test
     */
    public function it_offers_nothing_for_a_content_type_without_urls_of_its_own(): void
    {
        $recipe = new Recipe();
        $recipe->setCurrentLocale('en_US');
        $recipe->setTitle('Pancakes');
        ChannelsAndAudience::modifier()->setChannels($recipe, ['WEB']);

        self::assertSame([], $this->entryShareLinks()->targets($recipe));
    }

    /**
     * @param EntryInterface|null $entry an entry whose working copy is $workingCopy, while every other entry is its own
     */
    private function entryShareLinks(?EntryInterface $entry = null, ?EntryInterface $workingCopy = null): EntryShareLinks
    {
        $workingCopies = $this->prophesize(WorkingCopyProviderInterface::class);
        $workingCopies->getWorkingCopy(Argument::type(EntryInterface::class))->willReturnArgument(0);
        if (null !== $entry && null !== $workingCopy) {
            $workingCopies->getWorkingCopy($entry)->willReturn($workingCopy);
        }

        $contentTypes = new ContentTypeRegistry([
            'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
            'recipe' => ['model' => Recipe::class, 'translation' => null, 'resource' => 'app.recipe'],
        ]);

        $routes = new RouteCollection();
        $routes->add('setono_sylius_cms_shop_entry', new Route('/{_locale}/{path}', requirements: ['path' => '.+(?<!/)']));

        return new EntryShareLinks(
            $this->shareLinks->reveal(),
            new ShareLinkUrlGenerator(
                $workingCopies->reveal(),
                $contentTypes,
                $this->channels->reveal(),
                new UrlGenerator($routes, new RequestContext('', 'GET', 'admin.example.com', 'https')),
                SnippetPreviews::hostPages($this->prophesize(PlacementRepositoryInterface::class)->reveal()),
                SnippetPreviews::samples(),
                SnippetPreviews::renderContexts(),
            ),
            $this->channels->reveal(),
            $workingCopies->reveal(),
            $contentTypes,
            new SlotRegistry([]),
        );
    }

    /**
     * @param array<string, string> $slugs
     * @param list<string> $channelCodes
     */
    private static function page(array $slugs, array $channelCodes): Page
    {
        $page = new Page();
        foreach ($slugs as $localeCode => $slug) {
            $page->setCurrentLocale($localeCode);
            $page->setTitle('About us');
            $page->setSlug($slug);
        }
        ChannelsAndAudience::modifier()->setChannels($page, $channelCodes);

        return $page;
    }

    private static function shareLink(EntryInterface $entry, string $channelCode, string $localeCode, string $token): ShareLink
    {
        $shareLink = new ShareLink();
        $shareLink->setEntry($entry);
        $shareLink->setChannelCode($channelCode);
        $shareLink->setLocaleCode($localeCode);
        $shareLink->setToken($token);

        return $shareLink;
    }

    /**
     * @param string|null $defaultLocaleCode the first of its locales, or null when it has no default locale
     */
    private static function channel(string $code, ?string $name, ?string $hostname, ?string $defaultLocaleCode, string ...$otherLocaleCodes): Channel
    {
        $channel = new Channel();
        $channel->setCode($code);
        $channel->setName($name);
        $channel->setHostname($hostname);
        foreach (null === $defaultLocaleCode ? $otherLocaleCodes : [$defaultLocaleCode, ...$otherLocaleCodes] as $localeCode) {
            $locale = new Locale();
            $locale->setCode($localeCode);
            $channel->addLocale($locale);
            if ($localeCode === $defaultLocaleCode) {
                $channel->setDefaultLocale($locale);
            }
        }

        return $channel;
    }
}
