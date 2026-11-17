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
use Setono\SyliusCMSPlugin\Tests\Support\ScheduledPublishes;
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
    use ScheduledPublishes;

    protected function setUp(): void
    {
        self::mockTime('2026-11-20 10:00:00');
        self::forgetSentMessages();
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
