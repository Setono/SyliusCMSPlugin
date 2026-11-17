<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Publishing\PublishSchedulerInterface;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyManagerInterface;
use Setono\SyliusCMSPlugin\Targeting\DateWindow;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\MediaItems;
use Setono\SyliusCMSPlugin\Tests\Support\ScheduledPublishes;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The Content hub's home: Needs attention, Coming up, Translations and Drafts in progress, and creating an entry from
 * it. See "Overview" in docs/spec/admin.md
 */
final class OverviewTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use Entries;
    use MediaItems;
    use ScheduledPublishes;

    private const HEADING = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f';

    private const PROMO = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e50';

    protected function setUp(): void
    {
        self::mockTime('2026-11-20 10:00:00');
        self::forgetSentMessages();
    }

    /**
     * Every content type is offered, so a new one, such as a project's or a built-in one, shows up here unchanged. See
     * "Create dialog" in docs/spec/admin.md
     *
     * @test
     */
    public function it_creates_an_entry_from_the_overview_through_the_create_dialog_which_asks_for_the_content_type_first(): void
    {
        $client = self::signedInClient();

        $crawler = $client->request('GET', '/admin/cms');

        self::assertResponseIsSuccessful();
        self::assertSame('cms-create-entry', $crawler->selectButton('Create')->attr('aria-controls'));
        self::assertSame(
            [['Announcement', '/admin/cms/entries/announcement/new'], ['Page', '/admin/cms/entries/page/new'], ['Snippet', '/admin/cms/entries/snippet/new']],
            $crawler->filter('dialog#cms-create-entry [data-cms-create-entry-type]')->each(static fn (Crawler $type): array => [trim($type->text()), (string) $type->attr('href')]),
        );
        // The panels sit in the order of the spec
        self::assertSame(
            ['needs_attention', 'coming_up', 'translations', 'drafts_in_progress'],
            $crawler->filter('[data-panel]')->each(static fn (Crawler $panel): ?string => $panel->attr('data-panel')),
        );
    }

    /**
     * @test
     */
    public function it_groups_the_stores_errors_and_warnings_by_kind_under_needs_attention_each_with_where_it_is_fixed(): void
    {
        $client = self::signedInClient();
        self::addLocales('WEB', 'da_DK');
        // An error and a warning
        $about = self::edited(self::createPageWithBlocks('About us', 'about-us', [
            new StoredBlock(self::PROMO, 'promo', 1),
            new StoredBlock(self::HEADING, 'heading', 1, fields: ['text' => ['en_US' => 'Hello', 'da_DK' => 42]]),
        ]), '2026-11-19 12:00:00');
        // A warning of the same kind
        $contact = self::edited(self::createPageWithBlocks('Contact', 'contact', [new StoredBlock(self::PROMO, 'promo', 1)]), '2026-11-19 11:00:00');
        // A time-dependent error, found as of now
        self::mockTime('2026-11-20 08:00:00');
        $overdue = self::pageWithDraft('Overdue', 'overdue');
        self::schedule($overdue, '2026-11-20 09:00:00');
        self::mockTime('2026-11-20 10:00:00');
        $overdue = self::edited(self::reloadPage($overdue), '2026-11-19 10:00:00');
        // Missing translations are never problems
        self::createPage('Clean', 'clean', 'Hello', published: true);

        $crawler = $client->request('GET', '/admin/cms');

        self::assertResponseIsSuccessful();
        $panel = $crawler->filter('[data-panel="needs_attention"]');
        self::assertSame('Needs attention', $panel->filter('h2')->text());
        $overview = static fn (EntryInterface $entry): string => sprintf('/admin/cms/entry/%s', $entry->getUuid());
        self::assertSame([
            ['misfit_value', 'error', 'Values that don\'t fit their field 1', [
                ['About us', $overview($about), 'Text in the Heading block holds a value that doesn\'t fit its field type, so it counts as empty.', sprintf('/admin/cms/entry/%s/builder?block=%s&locale=da_DK', $about->getUuid(), self::HEADING)],
            ]],
            ['scheduled_publish_overdue', 'error', 'Overdue scheduled publishes 1', [
                ['Overdue and more', $overview($overdue), 'The publish scheduled for 2026-11-20 09:00 hasn\'t run. The Messenger worker may not be running.', sprintf('/admin/cms/entry/%s#cms-section-publishing', $overdue->getUuid())],
            ]],
            ['unknown_block_type', 'warning', 'Blocks of removed types 2', [
                ['About us', $overview($about), 'A block of the type "promo", which the code no longer has, isn\'t shown.', sprintf('/admin/cms/entry/%s/builder?block=%s', $about->getUuid(), self::PROMO)],
                ['Contact', $overview($contact), 'A block of the type "promo", which the code no longer has, isn\'t shown.', sprintf('/admin/cms/entry/%s/builder?block=%s', $contact->getUuid(), self::PROMO)],
            ]],
        ], $panel->filter('[data-problem-kind]')->each(static fn (Crawler $group): array => [
            $group->attr('data-problem-kind'),
            $group->attr('data-severity'),
            $group->filter('h3')->text(),
            $group->filter('[data-entry]')->each(static fn (Crawler $problem): array => [
                $problem->filter('a.header')->text(),
                $problem->filter('a.header')->attr('href'),
                $problem->filter('.description')->text(),
                $problem->filter('a.button')->attr('href'),
            ]),
        ]));
        self::assertSame('/admin/cms/entries?criteria%5Bstatus%5D=needs_attention', $panel->selectLink('All 3 entries that need attention')->attr('href'));
    }

    /**
     * @test
     */
    public function it_says_when_nothing_needs_attention(): void
    {
        $client = self::signedInClient();
        self::createPage('About us', 'about-us', 'Hello', published: true);

        $crawler = $client->request('GET', '/admin/cms');

        $panel = $crawler->filter('[data-panel="needs_attention"]');
        self::assertCount(0, $panel->filter('[data-problem-kind]'));
        self::assertSame('Nothing needs attention.', $panel->filter('p')->text());
    }

    /**
     * Missing alt text is a flag, not a problem, and an image misses it when it has none in one or more of the locales
     * of the store's channels. See "Metadata" in docs/spec/media.md
     *
     * @test
     */
    public function it_counts_the_images_missing_alt_text_in_one_or_more_of_the_channels_locales_as_a_flag_linking_to_the_library(): void
    {
        $client = self::signedInClient();
        self::addLocales('WEB', 'da_DK');
        self::createLocale('fr_FR');
        $complete = $this->uploadMediaItem('complete.png', self::png());
        $complete->setAltText('en_US', 'A red square');
        $complete->setAltText('da_DK', 'En rød firkant');
        $danishMissing = $this->uploadMediaItem('danish-missing.png', self::png(rgb: [10, 200, 10]));
        $danishMissing->setAltText('en_US', 'A green square');
        // French is no channel's locale, so it doesn't stand in for Danish
        $danishMissing->setAltText('fr_FR', 'Un carré vert');
        $this->uploadMediaItem('none.png', self::png(rgb: [10, 10, 200]));
        // Only images have alt text
        $this->uploadMediaItem('price-list.pdf', self::pdf());
        self::entityManager()->flush();

        $crawler = $client->request('GET', '/admin/cms');

        $flag = $crawler->filter('[data-panel="needs_attention"] [data-flag="missing_alt_text"]');
        self::assertSame('2 images have no alt text in one or more languages', $flag->text());
        self::assertSame('/admin/cms/media?missingAlt=1', $flag->filter('a')->attr('href'));
        // It's a flag, not a problem
        self::assertCount(0, $crawler->filter('[data-panel="needs_attention"] [data-problem-kind]'));
        self::assertCount(0, $crawler->filter('[data-panel="needs_attention"] p'));
        self::assertSelectorNotExists('.sylius-admin-menu a[href="/admin/cms"] .label');
    }

    /**
     * @test
     */
    public function it_shows_no_flag_when_every_image_has_alt_text_in_every_channel_locale(): void
    {
        $client = self::signedInClient();
        $image = $this->uploadMediaItem('complete.png', self::png());
        $image->setAltText('en_US', 'A red square');
        self::entityManager()->flush();

        $crawler = $client->request('GET', '/admin/cms');

        self::assertCount(0, $crawler->filter('[data-panel="needs_attention"] [data-flag]'));
        self::assertSame('Nothing needs attention.', $crawler->filter('[data-panel="needs_attention"] p')->text());
    }

    /**
     * @test
     */
    public function it_shows_per_channel_and_locale_how_many_entries_are_complete_under_translations_linking_to_those_that_arent(): void
    {
        $client = self::signedInClient();
        self::addLocales('WEB', 'da_DK');
        self::createChannel('NORDIC', 'Nordic');
        self::addLocales('NORDIC', 'da_DK');
        $complete = self::createPageWithBlocks('About us', 'about-us', [
            new StoredBlock(self::HEADING, 'heading', 1, fields: ['text' => ['en_US' => 'Hello', 'da_DK' => 'Hej']]),
        ], channels: ['WEB', 'NORDIC']);
        self::addTranslation($complete, 'da_DK', 'Om os', 'om-os');
        // No Danish translation
        $english = self::createPage('English only', 'english-only', channels: ['WEB']);
        // The Danish translation's heading is untranslated
        $untranslated = self::createPage('Contact', 'contact', 'Hello', channels: ['WEB', 'NORDIC']);
        self::addTranslation($untranslated, 'da_DK', 'Kontakt', 'kontakt');
        self::createChannel('EMPTY', 'Empty');

        $crawler = $client->request('GET', '/admin/cms');

        $panel = $crawler->filter('[data-panel="translations"]');
        self::assertSame('Translations', $panel->filter('h2')->text());
        self::assertSame([
            ['WEB', 'Setono CMS Test Store', [
                ['en_US', 'English (United States)', '3 of 3 complete', null],
                ['da_DK', 'Danish (Denmark)', '1 of 3 complete', ['2 not complete', '/admin/cms/entries?criteria%5Bchannel%5D=WEB&criteria%5Btranslation%5D=da_DK']],
            ]],
            ['NORDIC', 'Nordic', [
                ['en_US', 'English (United States)', '2 of 2 complete', null],
                ['da_DK', 'Danish (Denmark)', '1 of 2 complete', ['1 not complete', '/admin/cms/entries?criteria%5Bchannel%5D=NORDIC&criteria%5Btranslation%5D=da_DK']],
            ]],
            ['EMPTY', 'Empty', []],
        ], $panel->filter('[data-channel]')->each(static fn (Crawler $channel): array => [
            $channel->attr('data-channel'),
            $channel->filter('h3')->text(),
            $channel->filter('[data-locale]')->each(static fn (Crawler $locale): array => [
                $locale->attr('data-locale'),
                $locale->filter('td')->eq(0)->text(),
                $locale->filter('td')->eq(1)->text(),
                0 === $locale->filter('a')->count() ? null : [$locale->filter('a')->text(), $locale->filter('a')->attr('href')],
            ]),
        ]));
        self::assertSame('No entries are in this channel.', $panel->filter('[data-channel="EMPTY"] p')->text());

        // The link lists the entries in the channel that aren't complete in the locale
        $crawler = $client->click($panel->selectLink('2 not complete')->link());

        self::assertResponseIsSuccessful();
        self::assertEqualsCanonicalizing([$english->getUuid(), $untranslated->getUuid()], $crawler->filter('table tbody [data-entry]')->each(static fn (Crawler $title): ?string => $title->attr('data-entry')));
        self::assertSame('da_DK', $crawler->filter('select[name="criteria[translation]"] option[selected]')->attr('value'));
        self::assertSame(
            ['Any', 'Not complete in English (United States)', 'Not complete in Danish (Denmark)'],
            $crawler->filter('select[name="criteria[translation]"] option')->each(static fn (Crawler $option): string => $option->text()),
        );
        self::assertSame('WEB', $crawler->filter('select[name="criteria[channel]"] option[selected]')->attr('value'));
    }

    /**
     * @test
     */
    public function it_lists_scheduled_publishes_and_date_windows_that_start_or_end_under_coming_up_in_time_order(): void
    {
        $client = self::signedInClient();

        // An overdue scheduled publish needs attention instead, and a failed one too
        self::mockTime('2026-11-20 08:00:00');
        self::schedule(self::pageWithDraft('Overdue', 'overdue'), '2026-11-20 09:00:00');
        $failed = self::pageWithDraft('Failed', 'failed');
        self::schedule($failed, '2026-11-20 09:30:00');
        $token = (string) self::scheduledPublishOf($failed)?->getToken();
        self::scheduler()->fail(self::reloadPage($failed), $token, 'A veto');
        self::mockTime('2026-11-20 10:00:00');

        $story = self::pageWithDraft('Story', 'story');
        self::schedule($story, '2026-11-20 14:00:00');
        $launch = self::withDateWindow(self::createPage('Launch', 'launch', published: true), '2026-11-21 09:00:00', null);
        $sale = self::withDateWindow(self::createPage('Sale', 'sale', published: true), null, '2026-11-22 18:00:00');
        // A window that started or ended before now has nothing coming up
        self::withDateWindow(self::createPage('Past', 'past', published: true), '2026-11-01 00:00:00', '2026-11-19 00:00:00');

        $crawler = $client->request('GET', '/admin/cms');

        self::assertResponseIsSuccessful();
        $panel = $crawler->filter('[data-panel="coming_up"]');
        self::assertSame('Coming up', $panel->filter('h2')->text());
        self::assertSame([
            ['publishes', '2026-11-20 14:00', 'Story and more', sprintf('/admin/cms/entry/%s', $story->getUuid()), 'Publishes · Page'],
            ['starts', '2026-11-21 09:00', 'Launch', sprintf('/admin/cms/entry/%s', $launch->getUuid()), 'Starts showing · Page'],
            ['ends', '2026-11-22 18:00', 'Sale', sprintf('/admin/cms/entry/%s', $sale->getUuid()), 'Stops showing · Page'],
        ], $panel->filter('[data-event]')->each(static fn (Crawler $event): array => [
            $event->attr('data-event'),
            $event->filter('time')->text(),
            $event->filter('a')->text(),
            $event->filter('a')->attr('href'),
            $event->filter('.description')->text(),
        ]));
    }

    /**
     * @test
     */
    public function it_says_when_nothing_is_coming_up(): void
    {
        $client = self::signedInClient();
        self::createPage('About us', 'about-us', published: true);

        $crawler = $client->request('GET', '/admin/cms');

        $panel = $crawler->filter('[data-panel="coming_up"]');
        self::assertCount(0, $panel->filter('[data-event]'));
        self::assertSame('No scheduled publishes, and no date windows that start or end.', $panel->filter('p')->text());
    }

    /**
     * @test
     */
    public function it_lists_the_entries_with_unpublished_changes_under_drafts_in_progress_the_one_edited_last_first(): void
    {
        $client = self::signedInClient();
        $older = self::createPage('Older', 'older', published: true);
        $newer = self::createPage('Newer', 'newer', published: true);
        self::mockTime('2026-11-18 09:00:00');
        self::saveChanges($older, [['op' => 'set_entry_value', 'field' => 'title', 'locale' => 'en_US', 'value' => 'Older, edited']]);
        self::mockTime('2026-11-19 16:30:00');
        self::saveChanges($newer, [['op' => 'set_entry_value', 'field' => 'title', 'locale' => 'en_US', 'value' => 'Newer, edited']]);
        // Neither one without changes since its publish, nor one never published, has a draft
        self::createPage('Published', 'published', published: true);
        self::createPage('Never published', 'never-published');

        $crawler = $client->request('GET', '/admin/cms');

        $panel = $crawler->filter('[data-panel="drafts_in_progress"]');
        self::assertSame('Drafts in progress', $panel->filter('h2')->text());
        self::assertSame([
            ['Newer, edited', sprintf('/admin/cms/entry/%s', $newer->getUuid()), 'Page · Last edited 2026-11-19 16:30 by John Doe'],
            ['Older, edited', sprintf('/admin/cms/entry/%s', $older->getUuid()), 'Page · Last edited 2026-11-18 09:00 by John Doe'],
        ], $panel->filter('[data-entry]')->each(static fn (Crawler $draft): array => [
            $draft->filter('a')->text(),
            $draft->filter('a')->attr('href'),
            $draft->filter('.description')->text(),
        ]));
        self::assertSame('/admin/cms/entries?criteria%5Bstatus%5D=unpublished_changes', $panel->selectLink('All entries with unpublished changes')->attr('href'));
    }

    /**
     * @test
     */
    public function it_says_when_no_drafts_are_in_progress(): void
    {
        $client = self::signedInClient();
        self::createPage('Never published', 'never-published');

        $crawler = $client->request('GET', '/admin/cms');

        $panel = $crawler->filter('[data-panel="drafts_in_progress"]');
        self::assertCount(0, $panel->filter('[data-entry]'));
        self::assertSame('No published entry has unpublished changes.', $panel->filter('p')->text());
    }

    private static function pageWithDraft(string $title, string $slug): PageInterface
    {
        $page = self::createPage($title, $slug, 'Hello', published: true);

        /** @var WorkingCopyManagerInterface $workingCopies */
        $workingCopies = self::getContainer()->get(WorkingCopyManagerInterface::class);
        $workingCopy = $workingCopies->getWorkingCopy($page);
        $workingCopy->setTitle($title . ' and more');
        $workingCopies->save($page, $workingCopy);

        return $page;
    }

    private static function schedule(EntryInterface $entry, string $at): void
    {
        self::scheduler()->schedule($entry, new \DateTimeImmutable($at));
    }

    private static function scheduler(): PublishSchedulerInterface
    {
        /** @var PublishSchedulerInterface $scheduler */
        $scheduler = self::getContainer()->get(PublishSchedulerInterface::class);

        return $scheduler;
    }

    /**
     * Sets when the entry was edited last, which orders what the panels list
     */
    private static function edited(PageInterface $page, string $at): PageInterface
    {
        $page->setLastEdited(new \DateTimeImmutable($at), null);

        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.page');
        $manager->flush();

        return $page;
    }

    private static function withDateWindow(PageInterface $page, ?string $from, ?string $until): PageInterface
    {
        $page->setDateWindow(new DateWindow(null === $from ? null : new \DateTimeImmutable($from), null === $until ? null : new \DateTimeImmutable($until)));

        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.page');
        $manager->flush();

        return $page;
    }

    private static function signedInClient(): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');

        return $client;
    }
}
