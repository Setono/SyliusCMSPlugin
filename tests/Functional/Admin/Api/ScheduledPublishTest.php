<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Event\BeforeEntryPublish;
use Setono\SyliusCMSPlugin\Event\EntryPublishCancelled;
use Setono\SyliusCMSPlugin\Event\EntryPublished;
use Setono\SyliusCMSPlugin\Event\EntryPublishFailed;
use Setono\SyliusCMSPlugin\Event\EntryPublishScheduled;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Publishing\ScheduledPublishDue;
use Setono\SyliusCMSPlugin\Tests\Support\Revisions;
use Setono\SyliusCMSPlugin\Tests\Support\ScheduledPublishes;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;

/**
 * Scheduling a publish from the builder: entry_schedule and entry_schedule_cancel, the lock on the draft while it waits,
 * and the Messenger worker running it. See "Scheduled publishes" in docs/spec/publishing.md
 */
final class ScheduledPublishTest extends BuilderApiTestCase
{
    use ClockSensitiveTrait;
    use Revisions;
    use ScheduledPublishes;

    private const KEY = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f';

    protected function setUp(): void
    {
        self::mockTime('2026-11-20 10:00:00');
        self::forgetSentMessages();
    }

    /**
     * @test
     */
    public function it_checks_the_draft_then_sends_a_message_delayed_until_its_time_on_sylius_main_transport_to_schedule(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        [$page, $csrfToken] = self::publishedPageWithDraft($client);
        $events = self::listen(BeforeEntryPublish::class, EntryPublishScheduled::class);

        $response = self::schedule($client, $page, '2026-11-20T11:30:00Z', $csrfToken);

        self::assertResponseIsSuccessful();
        self::assertIsArray($response);
        self::assertSame('unpublished_changes', $response['status'] ?? null);
        self::assertSame(['at' => '2026-11-20T11:30:00Z', 'by' => 'John Doe', 'status' => 'publishes_at_a_time', 'reason' => null], $response['schedule'] ?? null);

        $scheduledPublish = self::scheduledPublishOf($page);
        self::assertNotNull($scheduledPublish);
        self::assertSame('2026-11-20T11:30:00+00:00', $scheduledPublish->getPublishAt()->format(\DATE_ATOM));

        // One message, an hour and a half away, on the event bus and Sylius's main transport
        $messages = self::sentMessages();
        self::assertCount(1, $messages);
        $message = $messages[0]->getMessage();
        self::assertInstanceOf(ScheduledPublishDue::class, $message);
        self::assertSame($page->getUuid(), $message->entryUuid);
        self::assertSame($scheduledPublish->getToken(), $message->token);
        self::assertSame(5_400_000, $messages[0]->last(DelayStamp::class)?->getDelay());
        self::assertSame('sylius.event_bus', $messages[0]->last(BusNameStamp::class)?->getBusName());
        self::assertSame('main', $messages[0]->last(SentStamp::class)?->getSenderAlias());

        self::assertCount(2, $events);
        [$before, $scheduled] = $events->getArrayCopy();
        self::assertInstanceOf(BeforeEntryPublish::class, $before);
        self::assertTrue($before->scheduled);
        self::assertSame('Hello world', self::textOf($before->entry), 'Listeners check the draft');
        self::assertInstanceOf(EntryPublishScheduled::class, $scheduled);
        self::assertSame($page->getUuid(), $scheduled->uuid);
        self::assertSame('2026-11-20T11:30:00+00:00', $scheduled->at->format(\DATE_ATOM));

        // entry_show says so too
        $shown = self::json($client, 'GET', '/admin/cms/api/entries/' . $page->getUuid());
        self::assertIsArray($shown);
        self::assertIsArray($shown['schedule'] ?? null);
        self::assertSame('publishes_at_a_time', $shown['schedule']['status'] ?? null);
    }

    /**
     * @test
     */
    public function it_refuses_saves_discards_and_restores_while_a_scheduled_publish_waits_and_unlocks_the_draft_on_cancel(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        [$page, $csrfToken] = self::publishedPageWithDraft($client);
        $revision = self::storeRevision(self::reloadPage($page), [self::heading(self::KEY, 'Hi')]);
        self::schedule($client, $page, '2026-11-20T11:30:00Z', $csrfToken);
        $events = self::listen(EntryPublishCancelled::class);

        $response = self::saveHeading($client, $page, 'Hello there', $csrfToken);
        self::assertResponseStatusCodeSame(423);
        self::assertIsArray($response);
        self::assertIsString($response['error'] ?? null);

        self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/discard', $page->getUuid()), null, $csrfToken);
        self::assertResponseStatusCodeSame(423);

        self::json($client, 'POST', sprintf('/admin/cms/api/revisions/%d/restore', (int) $revision->getId()), null, $csrfToken);
        self::assertResponseStatusCodeSame(423);

        self::assertSame('Hello world', self::textOf(self::workingCopyOf($client, $page)), 'Nothing changed the draft');

        $response = self::json($client, 'DELETE', sprintf('/admin/cms/api/entries/%s/schedule', $page->getUuid()), null, $csrfToken);
        self::assertResponseIsSuccessful();
        self::assertIsArray($response);
        self::assertNull($response['schedule']);
        self::assertSame('unpublished_changes', $response['status'] ?? null);
        self::assertNull(self::scheduledPublishOf($page));
        self::assertCount(1, $events);
        $cancelled = $events[0];
        self::assertInstanceOf(EntryPublishCancelled::class, $cancelled);
        self::assertSame($page->getUuid(), $cancelled->uuid);

        self::saveHeading($client, $page, 'Hello there', $csrfToken);
        self::assertResponseIsSuccessful();
        self::assertSame('Hello there', self::textOf(self::workingCopyOf($client, $page)));
    }

    /**
     * @test
     */
    public function it_publishes_the_draft_as_a_scheduled_publish_by_who_scheduled_it_when_the_message_is_consumed(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        [$page, $csrfToken] = self::publishedPageWithDraft($client);
        self::schedule($client, $page, '2026-11-20T11:30:00Z', $csrfToken);
        $events = self::listen(BeforeEntryPublish::class, EntryPublished::class);

        // Nothing is due before its time
        self::assertSame(0, self::consumeMessages());

        self::mockTime('2026-11-20 11:30:01');
        self::assertSame(1, self::consumeMessages());

        self::assertCount(1, self::acknowledgedMessages(), 'The message is done');
        self::assertNull(self::scheduledPublishOf($page));
        self::assertNull(self::draftOf($page));
        $published = self::reloadPage($page);
        self::assertSame('Hello world', self::textOf($published));
        self::assertSame('2026-11-20 11:30:01', $published->getLastPublishedAt()?->format('Y-m-d H:i:s'));

        [$revision] = self::revisionsOf($page);
        self::assertTrue($revision->isScheduled());
        self::assertSame('John Doe', $revision->getPublishedBy());

        self::assertCount(2, $events);
        [$before, $publishedEvent] = $events->getArrayCopy();
        self::assertInstanceOf(BeforeEntryPublish::class, $before);
        self::assertTrue($before->scheduled);
        self::assertInstanceOf(EntryPublished::class, $publishedEvent);
        self::assertTrue($publishedEvent->scheduled);
        self::assertSame($page->getUuid(), $publishedEvent->uuid);
    }

    /**
     * @test
     */
    public function it_schedules_the_first_publish_of_a_never_published_entry_and_locks_its_rows(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $page = self::createPage('About us', 'about-us', 'Hello');
        $csrfToken = self::openBuilder($client, $page);

        $response = self::schedule($client, $page, '2026-11-21T08:00:00+01:00', $csrfToken);
        self::assertIsArray($response);
        self::assertSame('never_published', $response['status'] ?? null);
        self::assertIsArray($response['schedule'] ?? null);
        self::assertSame('2026-11-21T07:00:00Z', $response['schedule']['at'] ?? null);

        self::saveHeading($client, $page, 'Hello world', $csrfToken);
        self::assertResponseStatusCodeSame(423);

        self::mockTime('2026-11-21 08:00:00');
        self::consumeMessages();

        $published = self::reloadPage($page);
        self::assertTrue($published->isPublished());
        self::assertSame('Hello', self::textOf($published));
    }

    /**
     * @test
     */
    public function it_flags_the_entry_as_failed_with_the_reason_of_a_veto_when_it_runs_and_does_not_retry(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        [$page, $csrfToken] = self::publishedPageWithDraft($client);
        self::schedule($client, $page, '2026-11-20T11:30:00Z', $csrfToken);

        // Something that changed since scheduling stops it when it runs
        self::eventDispatcher()->addListener(BeforeEntryPublish::class, static fn (BeforeEntryPublish $event) => $event->veto('The campaign was postponed'));
        $events = self::listen(EntryPublishFailed::class, EntryPublished::class);

        self::mockTime('2026-11-20 11:31:00');
        self::assertSame(1, self::consumeMessages());

        // The worker handled it without an error, so it's done, and isn't sent again to retry
        self::assertCount(1, self::acknowledgedMessages());
        self::assertCount(1, self::sentMessages());
        self::assertCount(1, $events);
        $failed = $events[0];
        self::assertInstanceOf(EntryPublishFailed::class, $failed);
        self::assertSame($page->getUuid(), $failed->uuid);
        self::assertSame('The campaign was postponed', $failed->reason);

        $scheduledPublish = self::scheduledPublishOf($page);
        self::assertNotNull($scheduledPublish);
        self::assertTrue($scheduledPublish->isFailed());
        self::assertSame('The campaign was postponed', $scheduledPublish->getFailureReason());
        self::assertSame('Hello', self::textOf(self::reloadPage($page)), 'Nothing was published');

        $shown = self::json($client, 'GET', '/admin/cms/api/entries/' . $page->getUuid());
        self::assertIsArray($shown);
        self::assertSame(['at' => '2026-11-20T11:30:00Z', 'by' => 'John Doe', 'status' => 'scheduled_publish_failed', 'reason' => 'The campaign was postponed'], $shown['schedule'] ?? null);

        // The draft is unlocked
        self::saveHeading($client, $page, 'Hello there', $csrfToken);
        self::assertResponseIsSuccessful();
    }

    /**
     * @test
     */
    public function it_flags_it_as_failed_with_what_the_errors_found_when_it_runs_say(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        [$page, $csrfToken] = self::publishedPageWithDraft($client);
        self::schedule($client, $page, '2026-11-20T11:30:00Z', $csrfToken);

        // The draft loses a required value behind the lock's back, as a change in the code that makes a field required would
        $draft = self::draftOf($page);
        self::assertNotNull($draft);
        $draft->setContent(str_replace('{"en_US":"Hello world"}', '{}', $draft->getContent()));
        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.draft');
        $manager->flush();

        self::mockTime('2026-11-20 11:31:00');
        self::consumeMessages();

        $scheduledPublish = self::scheduledPublishOf($page);
        self::assertNotNull($scheduledPublish);
        self::assertSame('Validation found an error to fix: This field is required.', $scheduledPublish->getFailureReason());
    }

    /**
     * @test
     */
    public function it_does_nothing_with_the_message_of_a_cancelled_or_replaced_scheduled_publish(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        [$page, $csrfToken] = self::publishedPageWithDraft($client);
        $events = self::listen(EntryPublished::class);

        self::schedule($client, $page, '2026-11-20T11:30:00Z', $csrfToken);
        self::json($client, 'DELETE', sprintf('/admin/cms/api/entries/%s/schedule', $page->getUuid()), null, $csrfToken);
        self::mockTime('2026-11-20 11:31:00');
        self::assertSame(1, self::consumeMessages());

        self::assertCount(0, $events, 'A cancelled one does nothing');
        self::assertSame('Hello', self::textOf(self::reloadPage($page)));

        self::forgetSentMessages();
        self::schedule($client, $page, '2026-11-20T12:00:00Z', $csrfToken);
        self::schedule($client, $page, '2026-11-20T12:30:00Z', $csrfToken);
        self::mockTime('2026-11-20 12:31:00');
        self::assertSame(2, self::consumeMessages());

        self::assertCount(1, $events, 'The one it replaced does nothing, and it publishes once');
        self::assertSame('Hello world', self::textOf(self::reloadPage($page)));
    }

    /**
     * @test
     */
    public function it_refuses_a_time_that_is_not_in_the_future_and_a_draft_publishing_would_refuse(): void
    {
        $client = self::createClient();
        [$page, $csrfToken] = self::publishedPageWithDraft($client);

        $response = self::schedule($client, $page, '2026-11-20T10:00:00Z', $csrfToken);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'Pick a time in the future.'], $response);

        self::saveHeading($client, $page, null, $csrfToken);
        $response = self::schedule($client, $page, '2026-11-20T11:00:00Z', $csrfToken);
        self::assertResponseStatusCodeSame(422);
        self::assertIsArray($response);
        self::assertSame('One error needs fixing', $response['error'] ?? null);
        self::assertSame([['block' => self::KEY, 'field' => 'text', 'locale' => 'en_US', 'message' => 'This field is required.']], $response['errors'] ?? null);

        self::assertNull(self::scheduledPublishOf($page));
        self::assertSame([], self::sentMessages());
    }

    /**
     * @test
     *
     * @dataProvider provideBodiesWithoutATime
     */
    public function it_answers_400_to_a_body_without_a_time(string $body): void
    {
        $client = self::createClient();
        [$page, $csrfToken] = self::publishedPageWithDraft($client);

        $client->request('POST', sprintf('/admin/cms/api/entries/%s/schedule', $page->getUuid()), [], [], ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $csrfToken], $body);

        self::assertResponseStatusCodeSame(400);
        self::assertNull(self::scheduledPublishOf($page));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideBodiesWithoutATime(): iterable
    {
        yield 'not JSON' => ['tomorrow'];
        yield 'no time' => ['{}'];
        yield 'a date without a time' => ['{"at":"2026-11-21"}'];
        yield 'a date that does not exist' => ['{"at":"2026-02-31T25:00:00Z"}'];
    }

    /**
     * @test
     */
    public function it_needs_the_builders_csrf_token_to_schedule_and_cancel(): void
    {
        $client = self::createClient();
        [$page] = self::publishedPageWithDraft($client);

        self::schedule($client, $page, '2026-11-20T11:30:00Z', 'not the token');
        self::assertResponseStatusCodeSame(403);

        self::json($client, 'DELETE', sprintf('/admin/cms/api/entries/%s/schedule', $page->getUuid()), null, 'not the token');
        self::assertResponseStatusCodeSame(403);
    }

    /**
     * A published page whose heading says Hello, with a draft whose heading says Hello world, and the builder's CSRF token
     *
     * @return array{PageInterface, string}
     */
    private static function publishedPageWithDraft(KernelBrowser $client): array
    {
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);

        $csrfToken = self::openBuilder($client, $page);
        self::saveHeading($client, $page, 'Hello world', $csrfToken);
        self::assertResponseIsSuccessful();

        return [$page, $csrfToken];
    }

    private static function schedule(KernelBrowser $client, PageInterface $page, string $at, string $csrfToken): mixed
    {
        return self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/schedule', $page->getUuid()), ['at' => $at], $csrfToken);
    }

    private static function saveHeading(KernelBrowser $client, PageInterface $page, ?string $text, string $csrfToken): mixed
    {
        return self::json($client, 'PATCH', '/admin/cms/api/entries/' . $page->getUuid(), ['lockToken' => self::lockToken($client, $page), 'changes' => [
            ['op' => 'set_value', 'key' => self::KEY, 'field' => 'text', 'locale' => 'en_US', 'value' => $text],
        ]], $csrfToken);
    }

    /**
     * The working copy entry_show answers with
     *
     * @return array<array-key, mixed>
     */
    private static function workingCopyOf(KernelBrowser $client, PageInterface $page): array
    {
        $response = self::json($client, 'GET', '/admin/cms/api/entries/' . $page->getUuid());
        self::assertIsArray($response);
        self::assertIsArray($response['entry'] ?? null);

        return $response['entry'];
    }

    /**
     * The heading's text: of an entry, or of a working copy in the entry format
     *
     * @param EntryInterface|array<array-key, mixed> $entry
     */
    private static function textOf(EntryInterface|array $entry): mixed
    {
        if (is_array($entry)) {
            /** @var array{tree: array{blocks: list<array{fields: array{text: array{en_US: mixed}}}>}} $workingCopy */
            $workingCopy = $entry;

            return $workingCopy['tree']['blocks'][0]['fields']['text']['en_US'] ?? null;
        }

        $block = $entry->getBlockTree()->findBlock(self::KEY);
        self::assertInstanceOf(StoredBlock::class, $block);
        $text = $block->fields['text'] ?? null;
        self::assertIsArray($text);

        return $text['en_US'] ?? null;
    }

    /**
     * Records the events of these classes as they're dispatched
     *
     * @param class-string ...$events
     *
     * @return \ArrayObject<int, object>
     */
    private static function listen(string ...$events): \ArrayObject
    {
        /** @var \ArrayObject<int, object> $dispatched */
        $dispatched = new \ArrayObject();
        foreach ($events as $event) {
            self::eventDispatcher()->addListener($event, static function (object $event) use ($dispatched): void {
                $dispatched->append($event);
            });
        }

        return $dispatched;
    }

    private static function eventDispatcher(): EventDispatcherInterface
    {
        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = self::getContainer()->get('event_dispatcher');

        return $eventDispatcher;
    }
}
