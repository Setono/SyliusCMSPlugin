<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin;

use Setono\SyliusCMSPlugin\Event\BeforeEntryPublish;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyManagerInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\ScheduledPublishes;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Scheduled publishes on the entry's overview page, in the lists and as problems: Schedule, Cancel the schedule, the
 * overdue warning with Publish now, and a failed one with its reason and Schedule again. See "Entry overview page",
 * "Status labels", "Entry lists" and "Problems" in docs/spec/admin.md
 */
final class ScheduledPublishOverviewTest extends WebTestCase
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
    public function it_lets_an_editor_schedule_a_publish_which_locks_the_draft_until_it_runs_or_is_cancelled(): void
    {
        $client = self::signedInClient();
        $page = self::pageWithDraft();
        $overview = sprintf('/admin/cms/entry/%s', $page->getUuid());

        $client->request('GET', $overview);
        $client->submitForm('Schedule', ['at' => '2026-11-20T11:30']);

        self::assertResponseRedirects($overview);
        $scheduledPublish = self::scheduledPublishOf($page);
        self::assertNotNull($scheduledPublish);
        // The admin shows and reads times in PHP's default time zone, which is UTC in the tests
        self::assertSame('2026-11-20T11:30:00+00:00', $scheduledPublish->getPublishAt()->format(\DATE_ATOM));
        self::assertSame('John Doe', $scheduledPublish->getScheduledBy());
        self::assertCount(1, self::sentMessages());

        $crawler = $client->followRedirect();
        self::assertSelectorTextContains('.sylius-flash-message', 'The publish has been scheduled for 2026-11-20 11:30 (UTC).');
        self::assertSame(['Published', 'Unpublished changes', 'Publishes 2026-11-20 11:30'], self::labels($crawler));

        $publishing = $crawler->filter('[data-section="publishing"]');
        $message = $publishing->filter('[data-scheduled-publish]');
        self::assertSame('publishes_at_a_time', $message->attr('data-scheduled-publish'));
        self::assertSame('Publishes 2026-11-20 11:30 (UTC)', $message->filter('.header')->text());
        self::assertSame('Scheduled by John Doe. The draft is locked until then. Cancel the schedule to change it.', $message->filter('p')->text());
        // It waits, so the only thing to do is cancel it
        self::assertSame(['Cancel the schedule'], self::buttons($publishing));

        $client->submitForm('Cancel the schedule');

        self::assertResponseRedirects($overview);
        self::assertNull(self::scheduledPublishOf($page));
        $crawler = $client->followRedirect();
        self::assertSelectorTextContains('.sylius-flash-message', 'The scheduled publish has been cancelled.');
        self::assertSame(['Published', 'Unpublished changes'], self::labels($crawler));
        self::assertSame(['Publish', 'Discard changes', 'Schedule'], self::buttons($crawler->filter('[data-section="publishing"]')));
    }

    /**
     * @test
     */
    public function it_offers_no_schedule_for_an_entry_with_nothing_to_publish(): void
    {
        $client = self::signedInClient();
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);

        $crawler = $client->request('GET', sprintf('/admin/cms/entry/%s', $page->getUuid()));

        self::assertSame([], self::buttons($crawler->filter('[data-section="publishing"]')));
    }

    /**
     * @test
     *
     * @dataProvider provideRefusedSchedules
     */
    public function it_says_why_it_did_not_schedule(string $at, string $flash): void
    {
        $client = self::signedInClient();
        $page = self::pageWithDraft();

        $client->request('GET', sprintf('/admin/cms/entry/%s', $page->getUuid()));
        $client->submitForm('Schedule', ['at' => $at]);
        $client->followRedirect();

        self::assertSelectorTextContains('.sylius-flash-message.negative', $flash);
        self::assertNull(self::scheduledPublishOf($page));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideRefusedSchedules(): iterable
    {
        yield 'no time' => ['', 'Pick when to publish.'];
        yield 'now' => ['2026-11-20T10:00', 'Pick a time in the future.'];
    }

    /**
     * @test
     */
    public function it_says_how_many_errors_need_fixing_when_validation_refuses_a_schedule(): void
    {
        $client = self::signedInClient();
        $page = self::createPageWithBlocks('About us', 'about-us', [new StoredBlock('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f', 'heading', 1)]);

        $client->request('GET', sprintf('/admin/cms/entry/%s', $page->getUuid()));
        $client->submitForm('Schedule', ['at' => '2026-11-21T09:00']);
        $client->followRedirect();

        self::assertSelectorTextContains('.sylius-flash-message.negative', 'It wasn\'t scheduled: one error needs fixing.');
        self::assertNull(self::scheduledPublishOf($page));
    }

    /**
     * @test
     */
    public function it_refuses_to_discard_the_draft_while_a_scheduled_publish_locks_it(): void
    {
        $client = self::signedInClient();
        $page = self::pageWithDraft();
        $overview = sprintf('/admin/cms/entry/%s', $page->getUuid());
        $crawler = $client->request('GET', $overview);
        $csrfToken = (string) $crawler->filter('[data-schedule-form] input[name="_csrf_token"]')->attr('value');
        $client->submitForm('Schedule', ['at' => '2026-11-20T11:30']);

        $client->request('POST', sprintf('/admin/cms/entry/%s/discard', $page->getUuid()), ['_csrf_token' => $csrfToken]);
        $client->followRedirect();

        self::assertSelectorTextContains('.sylius-flash-message.negative', 'The draft is locked until its scheduled publish runs.');
        self::assertNotNull(self::draftOf($page));
    }

    /**
     * @test
     */
    public function it_warns_that_the_worker_may_not_be_running_for_an_overdue_scheduled_publish_and_offers_publish_now(): void
    {
        $client = self::signedInClient();
        $page = self::pageWithDraft();
        $overview = sprintf('/admin/cms/entry/%s', $page->getUuid());
        $client->request('GET', $overview);
        $client->submitForm('Schedule', ['at' => '2026-11-20T11:30']);

        // Four minutes after its time it isn't overdue yet
        self::mockTime('2026-11-20 11:34:59');
        $crawler = $client->request('GET', $overview);
        self::assertSame('publishes_at_a_time', $crawler->filter('[data-scheduled-publish]')->attr('data-scheduled-publish'));
        self::assertCount(0, $crawler->filter('[data-section="problems"]'));

        self::mockTime('2026-11-20 11:35:00');
        $crawler = $client->request('GET', $overview);

        self::assertSame(['Published', 'Unpublished changes', 'Scheduled publish overdue'], self::labels($crawler));
        $publishing = $crawler->filter('[data-section="publishing"]');
        $message = $publishing->filter('[data-scheduled-publish]');
        self::assertSame('scheduled_publish_overdue', $message->attr('data-scheduled-publish'));
        self::assertSame('The publish scheduled for 2026-11-20 11:30 (UTC) hasn\'t run', $message->filter('.header')->text());
        self::assertSame('The Messenger worker may not be running. Publish now, or cancel the schedule.', $message->filter('p')->text());
        self::assertSame(['Publish now', 'Cancel the schedule'], self::buttons($publishing));

        // An error, fixed in the Publishing section
        $problem = $crawler->filter('[data-section="problems"] [data-problem]');
        self::assertCount(1, $problem);
        self::assertSame(['scheduled_publish_overdue', 'error'], [$problem->attr('data-problem'), $problem->attr('data-severity')]);
        self::assertSame('The publish scheduled for 2026-11-20 11:30 hasn\'t run. The Messenger worker may not be running.', $problem->filter('.description')->text());
        self::assertSame($overview . '#cms-section-publishing', $problem->selectLink('Go to Publishing')->attr('href'));

        $client->submitForm('Publish now');

        self::assertNull(self::scheduledPublishOf($page));
        self::assertNull(self::draftOf($page));
        $crawler = $client->followRedirect();
        self::assertSelectorTextContains('.sylius-flash-message', 'The entry has been published.');
        self::assertSame(['Published'], self::labels($crawler));
    }

    /**
     * @test
     */
    public function it_shows_the_reason_of_a_failed_scheduled_publish_and_offers_schedule_again(): void
    {
        $client = self::signedInClient();
        $client->disableReboot();
        $page = self::pageWithDraft();
        $overview = sprintf('/admin/cms/entry/%s', $page->getUuid());
        $client->request('GET', $overview);
        $client->submitForm('Schedule', ['at' => '2026-11-20T11:30']);

        // Something that changed since stops it when it runs, and only then
        $vetoing = new \ArrayObject([true]);
        self::eventDispatcher()->addListener(BeforeEntryPublish::class, static function (BeforeEntryPublish $event) use ($vetoing): void {
            if (true === $vetoing[0]) {
                $event->veto('The campaign was postponed');
            }
        });
        self::mockTime('2026-11-20 11:31:00');
        self::consumeMessages();
        $vetoing[0] = false;

        $crawler = $client->request('GET', $overview);

        self::assertSame(['Published', 'Unpublished changes', 'Scheduled publish failed'], self::labels($crawler));
        $publishing = $crawler->filter('[data-section="publishing"]');
        $message = $publishing->filter('[data-scheduled-publish]');
        self::assertSame('scheduled_publish_failed', $message->attr('data-scheduled-publish'));
        self::assertSame('The publish scheduled for 2026-11-20 11:30 (UTC) was stopped when it ran', $message->filter('.header')->text());
        self::assertSame('The campaign was postponed', $message->filter('[data-reason]')->text());
        // The draft is unlocked, so it can be published, discarded or scheduled again, or the failure dismissed
        self::assertSame(['Publish', 'Discard changes', 'Schedule again', 'Dismiss'], self::buttons($publishing));

        $problem = $crawler->filter('[data-section="problems"] [data-problem]');
        self::assertSame(['scheduled_publish_failed', 'error'], [$problem->attr('data-problem'), $problem->attr('data-severity')]);
        self::assertSame('The publish scheduled for 2026-11-20 11:30 was stopped: The campaign was postponed', $problem->filter('.description')->text());

        $client->submitForm('Schedule again', ['at' => '2026-11-20T12:00']);

        $scheduledPublish = self::scheduledPublishOf($page);
        self::assertNotNull($scheduledPublish);
        self::assertFalse($scheduledPublish->isFailed());
        self::assertSame('2026-11-20T12:00:00+00:00', $scheduledPublish->getPublishAt()->format(\DATE_ATOM));
    }

    /**
     * @test
     */
    public function it_clears_a_failed_scheduled_publish_when_it_is_dismissed(): void
    {
        $client = self::signedInClient();
        $client->disableReboot();
        $page = self::pageWithDraft();
        $overview = sprintf('/admin/cms/entry/%s', $page->getUuid());
        $client->request('GET', $overview);
        $client->submitForm('Schedule', ['at' => '2026-11-20T11:30']);
        self::eventDispatcher()->addListener(BeforeEntryPublish::class, static fn (BeforeEntryPublish $event) => $event->veto('The campaign was postponed'));
        self::mockTime('2026-11-20 11:31:00');
        self::consumeMessages();

        $client->request('GET', $overview);
        $client->submitForm('Dismiss');

        self::assertNull(self::scheduledPublishOf($page));
    }

    /**
     * @test
     */
    public function it_labels_scheduled_publishes_in_the_lists_and_lists_those_that_wait_under_the_scheduled_filter(): void
    {
        $client = self::signedInClient();
        $client->disableReboot();
        $waiting = self::pageWithDraft('About us', 'about-us');
        $overdue = self::pageWithDraft('Delivery', 'delivery');
        $failed = self::pageWithDraft('Imprint', 'imprint');
        self::pageWithDraft('Jobs', 'jobs');

        foreach ([[$failed, '2026-11-20T10:30'], [$overdue, '2026-11-20T10:40'], [$waiting, '2026-11-20T12:00']] as [$page, $at]) {
            $client->request('GET', sprintf('/admin/cms/entry/%s', $page->getUuid()));
            $client->submitForm('Schedule', ['at' => $at]);
        }

        self::eventDispatcher()->addListener(BeforeEntryPublish::class, static fn (BeforeEntryPublish $event) => $event->veto('The campaign was postponed'));
        self::mockTime('2026-11-20 10:31:00');
        self::consumeMessages();

        // The worker stopped running
        self::mockTime('2026-11-20 10:45:00');
        $crawler = $client->request('GET', '/admin/cms/entries/page');

        self::assertSame(['Published', 'Unpublished changes', 'Publishes 2026-11-20 12:00'], self::rowLabels($crawler, $waiting));
        self::assertSame(['Published', 'Unpublished changes', 'Scheduled publish overdue'], self::rowLabels($crawler, $overdue));
        self::assertSame(['Published', 'Unpublished changes', 'Scheduled publish failed'], self::rowLabels($crawler, $failed));

        $crawler = $client->request('GET', '/admin/cms/entries/page?' . http_build_query(['criteria' => ['status' => 'scheduled']]));

        self::assertResponseIsSuccessful();
        self::assertEqualsCanonicalizing([$waiting->getUuid(), $overdue->getUuid()], self::listedUuids($crawler));
        self::assertSame(
            ['Any status', 'Needs attention', 'Unpublished changes or never published', 'Unpublished changes', 'Never published', 'Scheduled', 'Disabled or outside its date window'],
            $crawler->filter('#criteria_status option')->each(static fn (Crawler $option): string => $option->text()),
        );

        // Overdue and failed need attention
        $crawler = $client->request('GET', '/admin/cms/entries/page?' . http_build_query(['criteria' => ['status' => 'needs_attention']]));
        self::assertEqualsCanonicalizing([$overdue->getUuid(), $failed->getUuid()], self::listedUuids($crawler));
    }

    /**
     * A published page, with a draft that changes its title
     */
    private static function pageWithDraft(string $title = 'About us', string $slug = 'about-us'): PageInterface
    {
        $page = self::createPage($title, $slug, 'Hello', published: true);

        /** @var WorkingCopyManagerInterface $workingCopies */
        $workingCopies = self::getContainer()->get(WorkingCopyManagerInterface::class);
        $workingCopy = $workingCopies->getWorkingCopy($page);
        $workingCopy->setTitle($title . ' and more');
        $workingCopies->save($page, $workingCopy);

        return $page;
    }

    private static function signedInClient(): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');

        return $client;
    }

    /**
     * The status labels in the overview page's header
     *
     * @return list<string>
     */
    private static function labels(Crawler $crawler): array
    {
        return self::texts($crawler->filter('[data-overview-header] [data-status] .label'));
    }

    /**
     * The status labels of the entry's row in a list
     *
     * @return list<string>
     */
    private static function rowLabels(Crawler $crawler, EntryInterface $entry): array
    {
        $row = $crawler->filterXPath(sprintf('//table/tbody/tr[.//*[@data-entry="%s"]]', $entry->getUuid()));
        self::assertCount(1, $row);

        return self::texts($row->filter('[data-column="status"] .label'));
    }

    /**
     * The buttons of a section's forms
     *
     * @return list<string>
     */
    private static function buttons(Crawler $section): array
    {
        return self::texts($section->filter('form button'));
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

    /**
     * @return list<string>
     */
    private static function listedUuids(Crawler $crawler): array
    {
        $uuids = [];
        foreach ($crawler->filter('table tbody [data-entry]') as $title) {
            self::assertInstanceOf(\DOMElement::class, $title);
            $uuids[] = $title->getAttribute('data-entry');
        }

        return $uuids;
    }

    private static function eventDispatcher(): EventDispatcherInterface
    {
        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = self::getContainer()->get('event_dispatcher');

        return $eventDispatcher;
    }
}
