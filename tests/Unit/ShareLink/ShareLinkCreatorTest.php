<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\ShareLink;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Factory\ShareLinkFactory;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\ShareLink;
use Setono\SyliusCMSPlugin\Model\ShareLinkInterface;
use Setono\SyliusCMSPlugin\Preview\StartingValuesInterface;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyProviderInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Repository\ShareLinkRepositoryInterface;
use Setono\SyliusCMSPlugin\ShareLink\ShareLinkCreator;
use Setono\SyliusCMSPlugin\ShareLink\ShareLinkUnavailableException;
use Setono\SyliusCMSPlugin\Tests\Support\ChannelsAndAudience;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;
use Sylius\Component\Channel\Model\ChannelInterface as BaseChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\AdminUser;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Locale\Model\Locale;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A share link is made for a channel and a locale the entry's draft has a URL in, with Shown to and Date fixed at the
 * preview's starting values, and records who made it. See "Share links" in docs/spec/publishing.md
 */
final class ShareLinkCreatorTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ShareLinkRepositoryInterface> */
    private ObjectProphecy $shareLinks;

    /** @var ObjectProphecy<ChannelRepositoryInterface<BaseChannelInterface>> */
    private ObjectProphecy $channels;

    /** @var ObjectProphecy<StartingValuesInterface> */
    private ObjectProphecy $startingValues;

    private TokenStorage $tokenStorage;

    /** @var list<ShareLinkInterface> */
    private array $added = [];

    protected function setUp(): void
    {
        $added = &$this->added;
        $this->shareLinks = $this->prophesize(ShareLinkRepositoryInterface::class);
        $this->shareLinks->add(Argument::type(ShareLinkInterface::class))->will(static function (array $arguments) use (&$added): void {
            self::assertInstanceOf(ShareLinkInterface::class, $arguments[0]);
            $added[] = $arguments[0];
        });

        /** @var ObjectProphecy<ChannelRepositoryInterface<BaseChannelInterface>> $channels */
        $channels = $this->prophesize(ChannelRepositoryInterface::class);
        $this->channels = $channels;
        $this->channels->findOneByCode(Argument::any())->willReturn(null);
        $this->channels->findOneByCode('WEB')->willReturn(self::channel('WEB', 'en_US', 'da_DK'));
        $this->channels->findOneByCode('B2B')->willReturn(self::channel('B2B', 'de_DE'));

        $this->startingValues = $this->prophesize(StartingValuesInterface::class);
        $this->startingValues->shownTo(Argument::any())->willReturn('@guest');
        $this->startingValues->date(Argument::any())->willReturn(null);

        $this->tokenStorage = new TokenStorage();
    }

    /**
     * @test
     */
    public function it_stores_a_share_link_of_the_entry_for_the_channel_and_locale_with_the_previews_starting_values(): void
    {
        $page = self::page(['en_US' => 'about-us', 'da_DK' => 'om-os'], ['WEB']);
        $date = new \DateTimeImmutable('2026-12-01 08:00:00 UTC');
        $this->startingValues->shownTo($page)->willReturn('wholesale');
        $this->startingValues->date($page)->willReturn($date);
        $this->signIn('jdoe', 'John', 'Doe');

        $shareLink = $this->creator()->create($page, 'WEB', 'da_DK');

        self::assertSame([$shareLink], $this->added);
        self::assertSame($page, $shareLink->getEntry());
        self::assertSame('WEB', $shareLink->getChannelCode());
        self::assertSame('da_DK', $shareLink->getLocaleCode());
        self::assertSame('wholesale', $shareLink->getShownTo());
        self::assertEquals($date, $shareLink->getDate());
        self::assertSame('2026-11-14T10:00:00+00:00', $shareLink->getCreatedAt()->format(\DATE_ATOM));
        self::assertSame('John Doe', $shareLink->getCreatedBy());
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $shareLink->getToken(), 'The share link factory gives it its token');
    }

    /**
     * @test
     */
    public function it_records_no_one_as_who_made_it_when_no_one_is_signed_in(): void
    {
        $shareLink = $this->creator()->create(self::page(['en_US' => 'about-us'], ['WEB']), 'WEB', 'en_US');

        self::assertNull($shareLink->getCreatedBy());
        self::assertNull($shareLink->getDate(), 'No date shows the page at the time the link is opened');
        self::assertSame('@guest', $shareLink->getShownTo());
    }

    /**
     * The draft's URL is the one the link opens
     *
     * @test
     */
    public function it_shares_a_locale_the_working_copy_has_a_url_in_while_the_rows_have_none(): void
    {
        $page = self::page(['en_US' => 'about-us'], ['WEB']);
        $workingCopy = clone $page;
        $workingCopy->setCurrentLocale('da_DK');
        $workingCopy->setTitle('Om os');
        $workingCopy->setSlug('om-os');

        $shareLink = $this->creator($page, $workingCopy)->create($page, 'WEB', 'da_DK');

        self::assertSame('da_DK', $shareLink->getLocaleCode());
    }

    /**
     * @test
     *
     * @dataProvider provideUnavailableShareLinks
     *
     * @param array<string, string> $slugs
     * @param list<string> $channelCodes
     */
    public function it_refuses_a_share_link_the_entry_has_no_page_for(array $slugs, array $channelCodes, string $channelCode, string $localeCode, string $message): void
    {
        $this->expectException(ShareLinkUnavailableException::class);
        $this->expectExceptionMessage($message);

        try {
            $this->creator()->create(self::page($slugs, $channelCodes), $channelCode, $localeCode);
        } finally {
            self::assertSame([], $this->added);
        }
    }

    /**
     * @return iterable<string, array{array<string, string>, list<string>, string, string, string}>
     */
    public static function provideUnavailableShareLinks(): iterable
    {
        yield 'a channel that does not exist' => [['en_US' => 'about-us'], ['WEB', 'GONE'], 'GONE', 'en_US', 'There is no channel GONE'];
        yield 'a channel the entry is not in' => [['en_US' => 'about-us', 'de_DE' => 'uber-uns'], ['WEB'], 'B2B', 'de_DE', 'The entry isn\'t in the channel B2B'];
        yield 'a locale the channel does not have' => [['en_US' => 'about-us', 'de_DE' => 'uber-uns'], ['WEB'], 'WEB', 'de_DE', 'The channel WEB has no locale de_DE'];
        yield 'no url in the locale' => [['en_US' => 'about-us'], ['WEB'], 'WEB', 'da_DK', 'The entry has no URL in da_DK'];
    }

    /**
     * @test
     */
    public function it_refuses_a_share_link_of_a_content_type_without_urls_of_its_own(): void
    {
        $recipe = new Recipe();
        $recipe->setCurrentLocale('en_US');
        $recipe->setTitle('Pancakes');
        ChannelsAndAudience::modifier()->setChannels($recipe, ['WEB']);

        $this->expectException(ShareLinkUnavailableException::class);
        $this->expectExceptionMessage('The entry has no URL in en_US');

        $this->creator()->create($recipe, 'WEB', 'en_US');
    }

    private function signIn(string $username, ?string $firstName = null, ?string $lastName = null): void
    {
        $admin = self::newAdmin();
        $admin->setUsername($username);
        $admin->setFirstName($firstName);
        $admin->setLastName($lastName);
        self::assertInstanceOf(UserInterface::class, $admin);
        $this->tokenStorage->setToken(new UsernamePasswordToken($admin, 'admin', []));
    }

    /**
     * Sylius's admin user is a Symfony user through a polyfill that static analysis doesn't follow
     */
    private static function newAdmin(): AdminUserInterface
    {
        return new AdminUser();
    }

    /**
     * @param EntryInterface|null $entry an entry whose working copy is $workingCopy, while every other entry is its own
     */
    private function creator(?EntryInterface $entry = null, ?EntryInterface $workingCopy = null): ShareLinkCreator
    {
        $workingCopies = $this->prophesize(WorkingCopyProviderInterface::class);
        $workingCopies->getWorkingCopy(Argument::type(EntryInterface::class))->willReturnArgument(0);
        if (null !== $entry && null !== $workingCopy) {
            $workingCopies->getWorkingCopy($entry)->willReturn($workingCopy);
        }

        return new ShareLinkCreator(
            new ShareLinkFactory(ShareLink::class),
            $this->shareLinks->reveal(),
            $this->channels->reveal(),
            $workingCopies->reveal(),
            new ContentTypeRegistry([
                'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
                'recipe' => ['model' => Recipe::class, 'translation' => null, 'resource' => 'app.recipe'],
            ]),
            $this->startingValues->reveal(),
            $this->tokenStorage,
            new MockClock('2026-11-14 10:00:00 UTC'),
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

    private static function channel(string $code, string ...$localeCodes): Channel
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
