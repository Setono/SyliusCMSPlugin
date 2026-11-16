<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Admin\EntryRows;
use Setono\SyliusCMSPlugin\Admin\WhereItShows;
use Setono\SyliusCMSPlugin\Admin\WhereItShowsCell;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Publishing\PublishSchedulerInterface;
use Setono\SyliusCMSPlugin\Query\VisibleEntriesQueryInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Routing\RouteCheckerInterface;
use Setono\SyliusCMSPlugin\Targeting\Audience;
use Setono\SyliusCMSPlugin\Targeting\DateWindow;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\ScheduledPublishes;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The overview page's Where it shows section: a grid of the store's channels and the locales of each, whose cells say
 * Live, or why shoppers don't see the entry there, with the audience below it. Its reasons agree with the
 * visible-entries query and the route check. See "Entry overview page" in docs/spec/admin.md and "When an entry shows"
 * in docs/spec/content-model.md
 */
final class WhereItShowsTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use Entries;
    use ScheduledPublishes;

    protected function setUp(): void
    {
        self::mockTime('2026-11-20 10:00:00');
        self::forgetSentMessages();
    }

    /**
     * @test
     */
    public function it_shows_live_or_why_not_for_each_locale_of_each_of_the_store_s_channels(): void
    {
        $client = self::signedInClient();
        self::addLocales('WEB', 'da_DK', 'sv_SE');
        self::createChannel('NORDIC', 'Nordic store');
        self::createChannel('OLD', 'Old store');
        self::disableChannel('OLD');
        $page = self::createPage('About us', 'about-us', published: true, channels: ['WEB', 'OLD']);
        self::addTranslation($page, 'da_DK', 'Om os', 'om-os', shown: false);

        $crawler = $client->request('GET', sprintf('/admin/cms/entry/%s', $page->getUuid()));

        self::assertResponseIsSuccessful();
        $section = $crawler->filter('[data-section="where_it_shows"]');
        self::assertSame('Where it shows', $section->filter('h2')->text());
        self::assertSame(['Channel', 'English (United States)', 'Danish (Denmark)', 'Swedish (Sweden)'], self::texts($section->filter('thead th')));
        self::assertSame(['Live', 'Held back', 'No translation'], self::cells($section, 'WEB'));
        self::assertSame(['live', 'held_back', 'no_translation'], self::reasons($section, 'WEB'));
        // A channel has cells only for its own locales
        self::assertSame('Nordic store', $section->filter('[data-channel="NORDIC"] th')->text());
        self::assertSame(['Not in the channel'], self::cells($section, 'NORDIC'));
        self::assertCount(2, $section->filter('[data-channel="NORDIC"] td.disabled'));
        // A disabled channel shows shoppers nothing, which its row says
        self::assertSame('Old store Disabled', $section->filter('[data-channel="OLD"] th')->text());
        self::assertSame(['Live'], self::cells($section, 'OLD'));

        self::assertSame('Audience: Everyone', $section->filter('[data-audience]')->text());
    }

    /**
     * @test
     *
     * @dataProvider provideEntriesThatShowNowhere
     */
    public function it_says_why_an_entry_shows_in_none_of_its_channels_and_locales(callable $craft, string $reason, string $text, bool $published = true): void
    {
        $client = self::signedInClient();
        $page = self::createPage('About us', 'about-us', published: $published);
        self::save($page, $craft);

        $section = $client->request('GET', sprintf('/admin/cms/entry/%s', $page->getUuid()))->filter('[data-section="where_it_shows"]');

        self::assertSame([$reason], self::reasons($section, 'WEB'));
        self::assertSame([$text], self::cells($section, 'WEB'));
    }

    /**
     * @return iterable<string, array{0: callable(PageInterface): void, 1: string, 2: string, 3?: bool}>
     */
    public static function provideEntriesThatShowNowhere(): iterable
    {
        yield 'disabled' => [static fn (PageInterface $page) => $page->setEnabled(false), 'disabled', 'Disabled'];
        yield 'not published yet' => [static function (): void {
        }, 'not_published_yet', 'Not published yet', false];
        yield 'ended' => [static fn (PageInterface $page) => $page->setDateWindow(new DateWindow(null, self::utc('2026-11-20 10:00:00'))), 'ended', 'Ended 2026-11-20 10:00'];
        yield 'shows from a date' => [static fn (PageInterface $page) => $page->setDateWindow(new DateWindow(self::utc('2026-12-01 08:00:00'))), 'shows_from_a_date', 'Shows from 2026-12-01 08:00'];
    }

    /**
     * @test
     */
    public function it_says_when_an_entry_never_published_publishes_while_its_scheduled_publish_waits(): void
    {
        $client = self::signedInClient();
        $page = self::createPage('About us', 'about-us');
        /** @var PublishSchedulerInterface $scheduler */
        $scheduler = self::getContainer()->get(PublishSchedulerInterface::class);
        $scheduler->schedule($page, self::utc('2026-11-21 09:00:00'));

        $section = $client->request('GET', sprintf('/admin/cms/entry/%s', $page->getUuid()))->filter('[data-section="where_it_shows"]');

        self::assertSame(['publishes_at_a_time'], self::reasons($section, 'WEB'));
        self::assertSame(['Publishes 2026-11-21 09:00'], self::cells($section, 'WEB'));
    }

    /**
     * @test
     */
    public function it_says_where_a_sylius_route_hides_the_entry(): void
    {
        $client = self::signedInClient();
        $page = self::createPage('Contact us', 'contact', published: true);

        $section = $client->request('GET', sprintf('/admin/cms/entry/%s', $page->getUuid()))->filter('[data-section="where_it_shows"]');

        self::assertSame(['hidden_by_route'], self::reasons($section, 'WEB'));
        self::assertSame(['Hidden by the Sylius route sylius_shop_contact_request'], self::cells($section, 'WEB'));
    }

    /**
     * The audience depends on the shopper, so it isn't a reason in a cell
     *
     * @test
     */
    public function it_shows_the_entry_s_audience_below_the_grid(): void
    {
        $client = self::signedInClient();
        $page = self::createPage('About us', 'about-us', published: true);
        self::save($page, static fn (PageInterface $page) => self::channelsAndAudience()->setAudience($page, Audience::only('@guest', '@no_group')));

        $section = $client->request('GET', sprintf('/admin/cms/entry/%s', $page->getUuid()))->filter('[data-section="where_it_shows"]');

        self::assertSame(['Live'], self::cells($section, 'WEB'));
        $audience = $section->filter('[data-audience]');
        self::assertSame('Audience:', $audience->filter('strong')->text());
        self::assertSame('Only Guests, Signed in without a group', $audience->filter('.label')->text());
        self::assertStringContainsString('Where it says Live, only shoppers in its audience see it.', $audience->text());
    }

    /**
     * Crafted entries, one for each reason and some live, checked in every channel and locale: a cell says Live exactly
     * where the visible-entries query finds the entry, for a shopper its audience allows, and no other route takes the
     * entry's URL
     *
     * @test
     */
    public function it_says_live_exactly_where_the_visible_entries_query_finds_the_entry_and_no_route_takes_its_url(): void
    {
        self::bootKernel();
        self::addLocales('WEB', 'da_DK');
        self::createChannel('NORDIC', 'Nordic store');
        self::addLocales('NORDIC', 'da_DK');

        $live = self::createPage('About us', 'about-us', published: true);
        self::addTranslation($live, 'da_DK', 'Om os', 'om-os', shown: true);
        $heldBack = self::createPage('Shipping', 'shipping', published: true, channels: ['NORDIC']);
        self::addTranslation($heldBack, 'da_DK', 'Fragt', 'fragt', shown: false);
        $disabled = self::createPage('Disabled', 'disabled', published: true);
        self::save($disabled, static fn (PageInterface $page) => $page->setEnabled(false));
        $ended = self::createPage('Ended', 'ended', published: true);
        self::save($ended, static fn (PageInterface $page) => $page->setDateWindow(new DateWindow(null, self::utc('2026-11-20 10:00:00'))));
        $later = self::createPage('Later', 'later', published: true);
        self::save($later, static fn (PageInterface $page) => $page->setDateWindow(new DateWindow(self::utc('2026-11-20 10:00:01'))));
        $now = self::createPage('Now', 'now', published: true);
        self::save($now, static fn (PageInterface $page) => $page->setDateWindow(new DateWindow(self::utc('2026-11-20 10:00:00'), self::utc('2026-11-20 10:00:01'))));
        $taken = self::createPage('Contact us', 'contact', published: true);
        self::addTranslation($taken, 'da_DK', 'Kontakt', 'kontakt', shown: true);
        $neverPublished = self::createPage('Never published', 'never-published');

        $checked = $liveCells = 0;
        foreach ([$live, $heldBack, $disabled, $ended, $later, $now, $taken, $neverPublished] as $page) {
            $page = self::reloadPage($page);
            foreach (self::grid($page) as $channelCode => $cells) {
                foreach ($cells as $localeCode => $cell) {
                    $visible = self::isVisible($page, self::channel($channelCode), $localeCode);
                    $slug = $page->getTranslations()->get($localeCode)?->getSlug();
                    $takenBy = null === $slug ? null : self::routeChecker()->takenBy(self::channel($channelCode), $localeCode, $slug);
                    $where = sprintf('%s in %s, %s', $page->getUuid(), $channelCode, $localeCode);

                    self::assertSame($visible && null === $takenBy, WhereItShowsCell::LIVE === $cell->code, $where);
                    if ($visible && null !== $takenBy) {
                        self::assertSame(WhereItShowsCell::HIDDEN_BY_ROUTE, $cell->code, $where);
                    }

                    ++$checked;
                    $liveCells += (int) (WhereItShowsCell::LIVE === $cell->code);
                }
            }
        }

        // Every page in both channels' two locales: About us is live everywhere, Now in English, Shipping in English in
        // the one channel it's in, and Contact us in Danish, where no route takes its URL
        self::assertSame(32, $checked);
        self::assertSame(9, $liveCells);
    }

    /**
     * @return array<string, array<string, WhereItShowsCell>> channel code => its cells by locale
     */
    private static function grid(PageInterface $page): array
    {
        /** @var EntryRows $rows */
        $rows = self::getContainer()->get(EntryRows::class);
        $rows->reset();
        /** @var WhereItShows $whereItShows */
        $whereItShows = self::getContainer()->get(WhereItShows::class);

        $grid = [];
        foreach ($whereItShows->of($rows->get($page))->channels as $channel) {
            $grid[$channel->code] = $channel->cells;
        }

        return $grid;
    }

    private static function isVisible(PageInterface $page, ChannelInterface $channel, string $localeCode): bool
    {
        /** @var VisibleEntriesQueryInterface $query */
        $query = self::getContainer()->get(VisibleEntriesQueryInterface::class);
        $context = new RenderContext($channel, $localeCode, 'USD', null, '@guest', Clock::get()->now());

        return null !== $query->createQueryBuilder('page', $context)
            ->andWhere('entry.uuid = :uuid')
            ->setParameter('uuid', $page->getUuid())
            ->getQuery()
            ->getOneOrNullResult();
    }

    private static function routeChecker(): RouteCheckerInterface
    {
        /** @var RouteCheckerInterface $routeChecker */
        $routeChecker = self::getContainer()->get(RouteCheckerInterface::class);

        return $routeChecker;
    }

    private static function channel(string $code): ChannelInterface
    {
        /** @var ChannelRepositoryInterface<ChannelInterface> $channels */
        $channels = self::getContainer()->get('sylius.repository.channel');
        $channel = $channels->findOneByCode($code);
        self::assertInstanceOf(ChannelInterface::class, $channel);

        return $channel;
    }

    private static function disableChannel(string $code): void
    {
        $channel = self::channel($code);
        $channel->setEnabled(false);

        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('sylius.manager.channel');
        $manager->flush();
    }

    /**
     * @param callable(PageInterface): mixed $change
     */
    private static function save(PageInterface $page, callable $change): void
    {
        $change($page);

        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.page');
        $manager->flush();
    }

    private static function signedInClient(): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');

        return $client;
    }

    /**
     * The text of each of the channel's cells, by locale
     *
     * @return list<string>
     */
    private static function cells(Crawler $section, string $channelCode): array
    {
        return self::texts($section->filter(sprintf('[data-channel="%s"] td[data-reason] [data-reason-text]', $channelCode)));
    }

    /**
     * @return list<string>
     */
    private static function reasons(Crawler $section, string $channelCode): array
    {
        $reasons = [];
        foreach ($section->filter(sprintf('[data-channel="%s"] td[data-reason]', $channelCode)) as $cell) {
            self::assertInstanceOf(\DOMElement::class, $cell);
            $reasons[] = $cell->getAttribute('data-reason');
        }

        return $reasons;
    }

    /**
     * @return list<string>
     */
    private static function texts(Crawler $crawler): array
    {
        $texts = [];
        foreach ($crawler as $node) {
            $texts[] = trim((string) preg_replace('/\s+/', ' ', $node->textContent));
        }

        return $texts;
    }

    private static function utc(string $at): \DateTimeImmutable
    {
        return new \DateTimeImmutable($at, new \DateTimeZone('UTC'));
    }
}
