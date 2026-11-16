<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Admin\EntryRow;
use Setono\SyliusCMSPlugin\Admin\WhereItShows;
use Setono\SyliusCMSPlugin\Admin\WhereItShowsCell;
use Setono\SyliusCMSPlugin\Admin\WhereItShowsChannel;
use Setono\SyliusCMSPlugin\Localization\Availability;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\ScheduledPublish;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Routing\TakenUrl;
use Setono\SyliusCMSPlugin\Routing\TakenUrlFinderInterface;
use Setono\SyliusCMSPlugin\Targeting\DateWindow;
use Setono\SyliusCMSPlugin\Tests\Support\ChannelsAndAudience;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Locale\Model\Locale;
use Symfony\Component\Clock\MockClock;

/**
 * The overview page's Where it shows grid: the store's channels and the locales of each, whose cells say Live, or why
 * shoppers don't see the entry there. A cell gives the first reason that applies, in the order the spec lists them.
 * See "Entry overview page" in docs/spec/admin.md and "When an entry shows" in docs/spec/content-model.md
 */
final class WhereItShowsTest extends TestCase
{
    use ProphecyTrait;

    private const NOW = '2026-11-27 12:00:00';

    /** @var ObjectProphecy<ChannelRepositoryInterface<ChannelInterface>> */
    private ObjectProphecy $channels;

    /** @var ObjectProphecy<TakenUrlFinderInterface> */
    private ObjectProphecy $takenUrls;

    protected function setUp(): void
    {
        /** @var ObjectProphecy<ChannelRepositoryInterface<ChannelInterface>> $channels */
        $channels = $this->prophesize(ChannelRepositoryInterface::class);
        $this->channels = $channels;
        $this->channels->findBy([], ['id' => 'ASC'])->willReturn([
            self::channel('WEB', 'Web store', 'en_US', 'da_DK', 'en_US'),
            self::channel('NORDIC', null, 'sv_SE', 'en_US', 'da_DK', 'sv_SE'),
        ]);
        $this->takenUrls = $this->prophesize(TakenUrlFinderInterface::class);
        $this->takenUrls->find(Argument::cetera())->willReturn([]);
    }

    /**
     * @test
     */
    public function its_grid_has_the_store_s_channels_and_each_channel_s_locales_its_default_locale_first(): void
    {
        $grid = $this->whereItShows()->of(self::row(self::page()));

        self::assertSame(['WEB', 'NORDIC'], array_map(static fn (WhereItShowsChannel $channel): string => $channel->code, $grid->channels));
        self::assertSame(['en_US', 'da_DK'], array_keys($grid->channels[0]->cells));
        self::assertSame(['sv_SE', 'da_DK', 'en_US'], array_keys($grid->channels[1]->cells));
        // Its columns are every locale of the channels, in the order the channels first have them
        self::assertSame(['en_US', 'da_DK', 'sv_SE'], $grid->localeCodes);
    }

    /**
     * @test
     */
    public function a_channel_is_named_by_its_name_or_else_by_its_code_and_says_whether_it_is_enabled(): void
    {
        $disabled = self::channel('B2B', 'Wholesale', 'en_US', 'en_US');
        $disabled->setEnabled(false);
        $this->channels->findBy([], ['id' => 'ASC'])->willReturn([self::channel('WEB', 'Web store', 'en_US', 'en_US'), self::channel('NORDIC', null, 'sv_SE', 'sv_SE'), $disabled]);

        $grid = $this->whereItShows()->of(self::row(self::page()));

        self::assertSame(['Web store', 'NORDIC', 'Wholesale'], array_map(static fn (WhereItShowsChannel $channel): string => $channel->name, $grid->channels));
        self::assertSame([true, true, false], array_map(static fn (WhereItShowsChannel $channel): bool => $channel->enabled, $grid->channels));
    }

    /**
     * @test
     */
    public function a_cell_is_live_where_the_entry_is_visible(): void
    {
        $page = self::page(['en_US' => 'About us', 'da_DK' => 'Om os']);

        self::assertEquals(new WhereItShowsCell(WhereItShowsCell::LIVE), $this->cell($page, 'WEB', 'en_US'));
        self::assertEquals(new WhereItShowsCell(WhereItShowsCell::LIVE), $this->cell($page, 'NORDIC', 'da_DK'));
    }

    /**
     * @test
     */
    public function a_cell_says_not_in_the_channel_in_a_channel_the_entry_is_not_in(): void
    {
        $page = self::page(['en_US' => 'About us', 'da_DK' => 'Om os']);
        ChannelsAndAudience::modifier()->setChannels($page, ['WEB']);

        self::assertEquals(new WhereItShowsCell(WhereItShowsCell::NOT_IN_CHANNEL), $this->cell($page, 'NORDIC', 'en_US'));
        self::assertEquals(new WhereItShowsCell(WhereItShowsCell::LIVE), $this->cell($page, 'WEB', 'en_US'));
    }

    /**
     * @test
     */
    public function a_cell_says_no_translation_in_a_locale_the_entry_has_no_translation_with_a_title_in(): void
    {
        $page = self::page(['en_US' => 'About us', 'sv_SE' => '']);

        self::assertEquals(new WhereItShowsCell(WhereItShowsCell::NO_TRANSLATION), $this->cell($page, 'WEB', 'da_DK'));
        self::assertEquals(new WhereItShowsCell(WhereItShowsCell::NO_TRANSLATION), $this->cell($page, 'NORDIC', 'sv_SE'));
    }

    /**
     * @test
     */
    public function a_cell_says_held_back_in_a_locale_whose_translation_is_held_back(): void
    {
        $page = self::page(['en_US' => 'About us', 'da_DK' => 'Om os'], heldBack: ['da_DK']);

        self::assertEquals(new WhereItShowsCell(WhereItShowsCell::HELD_BACK), $this->cell($page, 'WEB', 'da_DK'));
        self::assertEquals(new WhereItShowsCell(WhereItShowsCell::LIVE), $this->cell($page, 'WEB', 'en_US'));
    }

    /**
     * @test
     */
    public function a_cell_says_disabled_for_a_disabled_entry(): void
    {
        $page = self::page();
        $page->setEnabled(false);

        self::assertEquals(new WhereItShowsCell(WhereItShowsCell::DISABLED), $this->cell($page, 'WEB', 'en_US'));
    }

    /**
     * @test
     */
    public function a_cell_says_not_published_yet_for_an_entry_never_published(): void
    {
        $page = self::page(published: false);

        self::assertEquals(new WhereItShowsCell(WhereItShowsCell::NOT_PUBLISHED_YET), $this->cell($page, 'WEB', 'en_US'));
    }

    /**
     * @test
     */
    public function a_cell_says_when_an_entry_never_published_publishes_while_its_scheduled_publish_waits(): void
    {
        $page = self::page(published: false);
        $scheduledPublish = self::scheduledPublish('2026-11-28 09:00:00');

        self::assertEquals(
            new WhereItShowsCell(WhereItShowsCell::PUBLISHES_AT_A_TIME, ['at' => self::utc('2026-11-28 09:00:00')]),
            $this->cell($page, 'WEB', 'en_US', $scheduledPublish),
        );
    }

    /**
     * An overdue or failed scheduled publish isn't going to publish at its time any more
     *
     * @test
     */
    public function a_cell_says_not_published_yet_for_an_entry_never_published_whose_scheduled_publish_is_overdue_or_failed(): void
    {
        $page = self::page(published: false);
        $failed = self::scheduledPublish('2026-11-28 09:00:00');
        $failed->fail('Validation found an error to fix');

        self::assertEquals(new WhereItShowsCell(WhereItShowsCell::NOT_PUBLISHED_YET), $this->cell($page, 'WEB', 'en_US', self::scheduledPublish('2026-11-27 11:00:00')));
        self::assertEquals(new WhereItShowsCell(WhereItShowsCell::NOT_PUBLISHED_YET), $this->cell($page, 'WEB', 'en_US', $failed));
    }

    /**
     * A published entry's scheduled publish only publishes its draft's changes
     *
     * @test
     */
    public function a_scheduled_publish_does_not_stop_a_published_entry_from_being_live(): void
    {
        self::assertEquals(new WhereItShowsCell(WhereItShowsCell::LIVE), $this->cell(self::page(), 'WEB', 'en_US', self::scheduledPublish('2026-11-28 09:00:00')));
    }

    /**
     * "Show until" is exclusive, so a date window has ended once it's reached
     *
     * @test
     */
    public function a_cell_says_ended_once_the_entry_s_date_window_has_ended(): void
    {
        $page = self::page();
        $page->setDateWindow(new DateWindow(self::utc('2026-11-01 00:00:00'), self::utc(self::NOW)));

        self::assertEquals(new WhereItShowsCell(WhereItShowsCell::ENDED, ['until' => self::utc(self::NOW)]), $this->cell($page, 'WEB', 'en_US'));
    }

    /**
     * @test
     */
    public function a_cell_says_from_when_the_entry_shows_before_its_date_window_starts(): void
    {
        $page = self::page();
        $page->setDateWindow(new DateWindow(self::utc('2026-11-27 12:00:01')));

        self::assertEquals(new WhereItShowsCell(WhereItShowsCell::SHOWS_FROM_A_DATE, ['from' => self::utc('2026-11-27 12:00:01')]), $this->cell($page, 'WEB', 'en_US'));
    }

    /**
     * @test
     */
    public function a_cell_is_live_inside_the_entry_s_date_window(): void
    {
        $page = self::page();
        $page->setDateWindow(new DateWindow(self::utc(self::NOW), self::utc('2026-11-27 12:00:01')));

        self::assertEquals(new WhereItShowsCell(WhereItShowsCell::LIVE), $this->cell($page, 'WEB', 'en_US'));
    }

    /**
     * @test
     */
    public function a_cell_says_hidden_by_a_sylius_route_where_another_route_takes_the_entry_s_url(): void
    {
        $page = self::page(['en_US' => 'About us', 'da_DK' => 'Om os']);
        $this->takenUrls->find($page, ['en_US' => 'about-us', 'da_DK' => 'om-os'], ['NORDIC', 'WEB'], true)->willReturn([
            new TakenUrl('NORDIC', 'da_DK', 'om-os', 'app_about'),
        ]);

        $grid = $this->whereItShows()->of(self::row($page));

        self::assertEquals(new WhereItShowsCell(WhereItShowsCell::HIDDEN_BY_ROUTE, ['route' => 'app_about']), $grid->channels[1]->cells['da_DK']);
        self::assertEquals(new WhereItShowsCell(WhereItShowsCell::LIVE), $grid->channels[0]->cells['da_DK']);
        self::assertEquals(new WhereItShowsCell(WhereItShowsCell::LIVE), $grid->channels[1]->cells['en_US']);
    }

    /**
     * Only the URLs the entry would show at are checked: in its channels, and in the locales it shows in
     *
     * @test
     */
    public function it_checks_the_routes_of_the_urls_of_the_locales_the_entry_shows_in_in_its_channels_that_have_the_locale(): void
    {
        $page = self::page(['en_US' => 'About us', 'da_DK' => 'Om os', 'sv_SE' => null], heldBack: ['da_DK']);
        ChannelsAndAudience::modifier()->setChannels($page, ['NORDIC']);
        $this->takenUrls = $this->prophesize(TakenUrlFinderInterface::class);
        $this->takenUrls->find($page, ['en_US' => 'about-us'], ['NORDIC'], true)->willReturn([])->shouldBeCalledOnce();

        $this->whereItShows()->of(self::row($page));
    }

    /**
     * @test
     */
    public function it_checks_no_route_when_the_entry_shows_nowhere_whatever_its_urls(): void
    {
        $this->takenUrls->find(Argument::cetera())->shouldNotBeCalled();

        $disabled = self::page();
        $disabled->setEnabled(false);
        $nowhere = self::page();
        ChannelsAndAudience::modifier()->setChannels($nowhere, []);
        $heldBack = self::page(heldBack: ['en_US']);

        foreach ([$disabled, self::page(published: false), $nowhere, $heldBack] as $page) {
            $this->whereItShows()->of(self::row($page));
        }
    }

    /**
     * Each cell gives the first reason that applies, in the order the spec lists them: not in the channel, no
     * translation, held back, disabled, not published yet or publishes at a time, ended or shows from a date, and
     * hidden by a Sylius route
     *
     * @test
     */
    public function a_cell_gives_the_first_reason_that_applies(): void
    {
        $page = self::page(['en_US' => 'About us', 'da_DK' => 'Om os'], heldBack: ['da_DK'], published: false);
        ChannelsAndAudience::modifier()->setChannels($page, ['WEB']);
        $page->setEnabled(false);

        $grid = $this->whereItShows()->of(self::row($page, self::scheduledPublish('2026-11-28 09:00:00')));

        self::assertSame(WhereItShowsCell::NOT_IN_CHANNEL, $grid->channels[1]->cells['sv_SE']->code);
        self::assertSame(WhereItShowsCell::HELD_BACK, $grid->channels[0]->cells['da_DK']->code);
        self::assertSame(WhereItShowsCell::DISABLED, $grid->channels[0]->cells['en_US']->code);

        $withoutDanish = self::page(published: false);
        $withoutDanish->setEnabled(false);
        self::assertSame(WhereItShowsCell::NO_TRANSLATION, $this->cell($withoutDanish, 'WEB', 'da_DK')->code);

        $ended = self::page(published: false);
        $ended->setDateWindow(new DateWindow(null, self::utc(self::NOW)));
        self::assertSame(WhereItShowsCell::NOT_PUBLISHED_YET, $this->cell($ended, 'WEB', 'en_US')->code);
        self::assertSame(WhereItShowsCell::PUBLISHES_AT_A_TIME, $this->cell($ended, 'WEB', 'en_US', self::scheduledPublish('2026-11-28 09:00:00'))->code);

        $page = self::page();
        $page->setDateWindow(new DateWindow(null, self::utc(self::NOW)));
        $this->takenUrls->find(Argument::cetera())->willReturn([new TakenUrl('WEB', 'en_US', 'about-us', 'app_about')]);
        self::assertSame(WhereItShowsCell::ENDED, $this->cell($page, 'WEB', 'en_US')->code);
    }

    private function cell(Page $page, string $channelCode, string $localeCode, ?ScheduledPublish $scheduledPublish = null): WhereItShowsCell
    {
        foreach ($this->whereItShows()->of(self::row($page, $scheduledPublish))->channels as $channel) {
            if ($channel->code === $channelCode) {
                return $channel->cells[$localeCode];
            }
        }

        self::fail(sprintf('The grid has no channel %s', $channelCode));
    }

    private function whereItShows(): WhereItShows
    {
        return new WhereItShows($this->channels->reveal(), new Availability(), $this->takenUrls->reveal(), new MockClock(self::NOW, 'UTC'));
    }

    private static function row(Page $page, ?ScheduledPublish $scheduledPublish = null): EntryRow
    {
        $contentType = (new ContentTypeRegistry(['page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page']]))->get('page');

        return new EntryRow($page, $contentType, null, null, [], 'published', null, $scheduledPublish);
    }

    /**
     * A page in every channel, enabled and published unless said otherwise, with these titles, each with a slug of its own
     *
     * @param array<string, string|null> $titles by locale
     * @param list<string> $heldBack the locales whose translation is held back
     */
    private static function page(array $titles = ['en_US' => 'About us'], array $heldBack = [], bool $published = true): Page
    {
        $slugs = ['About us' => 'about-us', 'Om os' => 'om-os'];

        $page = new Page();
        foreach ($titles as $localeCode => $title) {
            $translation = new PageTranslation();
            $translation->setLocale($localeCode);
            $translation->setTitle($title);
            $translation->setSlug($slugs[$title ?? ''] ?? null);
            $translation->setShown(!in_array($localeCode, $heldBack, true));
            $page->addTranslation($translation);
        }
        ChannelsAndAudience::modifier()->setChannels($page, ['WEB', 'NORDIC']);

        if ($published) {
            $page->publish(self::utc('2026-11-01 10:00:00'));
        }

        return $page;
    }

    private static function scheduledPublish(string $at): ScheduledPublish
    {
        $scheduledPublish = new ScheduledPublish();
        $scheduledPublish->schedule(self::utc($at), 'Jane Roe');

        return $scheduledPublish;
    }

    private static function channel(string $code, ?string $name, string $defaultLocale, string ...$localeCodes): Channel
    {
        $channel = new Channel();
        $channel->setCode($code);
        $channel->setName($name);
        foreach ($localeCodes as $localeCode) {
            $locale = new Locale();
            $locale->setCode($localeCode);
            $channel->addLocale($locale);
            if ($localeCode === $defaultLocale) {
                $channel->setDefaultLocale($locale);
            }
        }

        return $channel;
    }

    private static function utc(string $at): \DateTimeImmutable
    {
        return new \DateTimeImmutable($at, new \DateTimeZone('UTC'));
    }
}
