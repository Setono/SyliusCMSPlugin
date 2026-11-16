<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Event\BeforeEntryPublish;
use Setono\SyliusCMSPlugin\Event\EntryCreated;
use Setono\SyliusCMSPlugin\Event\EntryPublished;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Drafts, publishing and discarding changes. See "Drafts" and "Publishing now" in docs/spec/publishing.md
 */
final class DraftTest extends BuilderApiTestCase
{
    private const KEY = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f';

    /**
     * @test
     */
    public function it_writes_the_rows_on_a_save_before_the_first_publish_and_makes_no_draft(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        $csrfToken = self::openBuilder($client, $page);

        $response = self::saveHeading($client, $page, 'Hello world', $csrfToken);

        self::assertSame(['status' => 'never_published', 'lockToken' => 1], $response);
        self::assertSame(['en_US' => 'Hello world'], self::reloadPage($page)->getBlockTree()->blocks[0]->fields['text'] ?? null);
        self::assertNull(self::draftOf($page));
    }

    /**
     * @test
     */
    public function it_creates_a_draft_on_a_save_after_a_publish_while_the_shop_still_shows_the_published_content(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);
        $rows = self::reloadPage($page)->getBlockTreeJson();
        $csrfToken = self::openBuilder($client, $page);

        $response = self::saveHeading($client, $page, 'Hello world', $csrfToken);

        self::assertResponseIsSuccessful();
        self::assertSame(['status' => 'unpublished_changes', 'lockToken' => 1], $response);
        self::assertSame($rows, self::reloadPage($page)->getBlockTreeJson());
        self::assertSame(
            sprintf(
                '{"uuid":"%s","contentType":"page","version":1,"createdAt":"%s","fields":{"noindex":false},"translations":{"en_US":{"shown":true,"fields":{"metaDescription":null,"metaTitle":null,"slug":"about-us","title":"About us"},"translated":{}}},"tree":{"layout":{"code":"standard","version":1,"fields":{},"translated":{}},"blocks":[{"key":"%s","type":"heading","version":1,"style":null,"fields":{"text":{"en_US":"Hello world"}},"translated":{},"conditions":[],"children":[]}]}}',
                $page->getUuid(),
                $page->getCreatedAt()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
                self::KEY,
            ),
            self::draftOf($page)?->getContent(),
        );

        $client->request('GET', '/en_US/about-us');
        self::assertSelectorTextSame('.cms-heading', 'Hello');

        // Later saves update the one draft
        self::saveHeading($client, $page, 'Hello again', $csrfToken);

        self::assertStringContainsString('"text":{"en_US":"Hello again"}', (string) self::draftOf($page)?->getContent());
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        self::assertEquals(1, $connection->fetchOne('SELECT COUNT(*) FROM setono_sylius_cms__draft'));
        self::assertSame($rows, self::reloadPage($page)->getBlockTreeJson());
    }

    /**
     * @test
     */
    public function it_shows_the_draft_as_the_working_copy_with_unpublished_changes(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);
        $csrfToken = self::openBuilder($client, $page);

        $client->request('GET', '/admin/cms/api/entries/' . $page->getUuid());
        self::assertSame('published', self::responseData($client)['status'] ?? null);

        self::saveHeading($client, $page, 'Hello world', $csrfToken);

        $client->request('GET', '/admin/cms/api/entries/' . $page->getUuid());
        self::assertSame(
            sprintf('{"entry":%s,"lockToken":1,%s"status":"unpublished_changes","editors":[],"completeness":{"en_US":{"untranslated":0,"outdated":0}}}', (string) self::draftOf($page)?->getContent(), '"settings":{"enabled":true,"showFrom":null,"showUntil":null,"channels":["WEB"],"audience":{"mode":"everyone","groups":[]},"homepageOf":[]},'),
            $client->getResponse()->getContent(),
        );
    }

    /**
     * @test
     */
    public function it_copies_the_draft_onto_the_rows_on_publish_and_deletes_it(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);
        $csrfToken = self::openBuilder($client, $page);
        self::saveHeading($client, $page, 'Hello world', $csrfToken);

        $response = self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/publish', $page->getUuid()), null, $csrfToken);

        self::assertResponseIsSuccessful();
        self::assertSame(['status' => 'published'], $response);
        self::assertNull(self::draftOf($page));
        self::assertSame(['en_US' => 'Hello world'], self::reloadPage($page)->getBlockTree()->blocks[0]->fields['text'] ?? null);

        $client->request('GET', '/en_US/about-us');
        self::assertSelectorTextSame('.cms-heading', 'Hello world');
    }

    /**
     * @test
     */
    public function it_answers_a_vetoed_publish_with_the_listeners_message(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);
        $csrfToken = self::openBuilder($client, $page);
        self::saveHeading($client, $page, 'Hello world', $csrfToken);

        self::eventDispatcher()->addListener(BeforeEntryPublish::class, static function (BeforeEntryPublish $event): void {
            $event->veto(sprintf('"%s" has to wait for Monday', (string) $event->entry->getTranslation('en_US')->getTitle()));
        });

        $response = self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/publish', $page->getUuid()), null, $csrfToken);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => '"About us" has to wait for Monday'], $response);
        self::assertNotNull(self::draftOf($page));
        self::assertSame(['en_US' => 'Hello'], self::reloadPage($page)->getBlockTree()->blocks[0]->fields['text'] ?? null);
    }

    /**
     * @test
     */
    public function it_deletes_the_draft_on_discard_and_answers_with_the_published_content(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);
        $csrfToken = self::openBuilder($client, $page);
        self::saveHeading($client, $page, 'Hello world', $csrfToken);

        $response = self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/discard', $page->getUuid()), null, $csrfToken);

        self::assertResponseIsSuccessful();
        self::assertSame(
            sprintf(
                '{"entry":{"uuid":"%s","contentType":"page","version":1,"createdAt":"%s","fields":{"noindex":false},"translations":{"en_US":{"shown":true,"fields":{"metaDescription":null,"metaTitle":null,"slug":"about-us","title":"About us"},"translated":{}}},"tree":{"layout":{"code":"standard","version":1,"fields":{},"translated":{}},"blocks":[{"key":"%s","type":"heading","version":1,"style":null,"fields":{"text":{"en_US":"Hello"}},"translated":{},"conditions":[],"children":[]}]}},"lockToken":2,"settings":{"enabled":true,"showFrom":null,"showUntil":null,"channels":["WEB"],"audience":{"mode":"everyone","groups":[]},"homepageOf":[]},"status":"published","editors":[],"completeness":{"en_US":{"untranslated":0,"outdated":0}}}',
                $page->getUuid(),
                $page->getCreatedAt()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
                self::KEY,
            ),
            $client->getResponse()->getContent(),
        );
        self::assertIsArray($response);
        self::assertNull(self::draftOf($page));
        self::assertSame(['en_US' => 'Hello'], self::reloadPage($page)->getBlockTree()->blocks[0]->fields['text'] ?? null);
    }

    /**
     * @test
     */
    public function it_needs_a_valid_csrf_token_to_discard(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);
        $csrfToken = self::openBuilder($client, $page);
        self::saveHeading($client, $page, 'Hello world', $csrfToken);

        self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/discard', $page->getUuid()), null, 'not-the-token');

        self::assertResponseStatusCodeSame(403);
        self::assertNotNull(self::draftOf($page));
    }

    /**
     * @test
     */
    public function it_needs_an_admin_user_to_discard(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);

        self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/discard', $page->getUuid()));

        self::assertResponseRedirects('http://localhost/admin/login');
    }

    /**
     * @test
     */
    public function it_bumps_changed_at_on_a_publish_and_not_on_a_draft_save(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        $page->publish(new \DateTimeImmutable('2026-01-01 10:00:00'));
        self::pageManager()->flush();
        $publishedAt = self::reloadPage($page)->getChangedAt();
        $createdAt = self::reloadPage($page)->getCreatedAt();
        $csrfToken = self::openBuilder($client, $page);

        self::saveHeading($client, $page, 'Hello world', $csrfToken);
        self::assertEquals($publishedAt, self::reloadPage($page)->getChangedAt());

        self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/publish', $page->getUuid()), null, $csrfToken);
        $published = self::reloadPage($page);
        self::assertGreaterThan($publishedAt, $published->getChangedAt());
        self::assertEquals($published->getLastPublishedAt(), $published->getChangedAt());
        self::assertEquals($createdAt, $published->getCreatedAt());
    }

    /**
     * @test
     */
    public function it_dispatches_before_entry_publish_in_the_publish_and_entry_published_after_it_commits_and_none_on_a_draft_save(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $page = self::createPage('About us', 'about-us', 'Hello');
        $page->publish(new \DateTimeImmutable('2026-01-01 10:00:00'));
        self::pageManager()->flush();
        $csrfToken = self::openBuilder($client, $page);

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $outside = $connection->getTransactionNestingLevel();

        /** @var \ArrayObject<int, array{object, int, bool}> $dispatched the event, the transaction nesting level, and whether the draft was gone */
        $dispatched = new \ArrayObject();
        foreach ([BeforeEntryPublish::class, EntryPublished::class, EntryCreated::class] as $event) {
            self::eventDispatcher()->addListener($event, static function (object $event) use ($dispatched, $connection, $page): void {
                $draftsLeft = $connection->fetchOne('SELECT COUNT(*) FROM setono_sylius_cms__draft WHERE entry_id = ?', [$page->getId()]);
                $dispatched->append([$event, $connection->getTransactionNestingLevel(), in_array($draftsLeft, [0, '0'], true)]);
            });
        }

        self::saveHeading($client, $page, 'Hello world', $csrfToken);
        self::assertCount(0, $dispatched);

        self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/publish', $page->getUuid()), null, $csrfToken);
        self::assertResponseIsSuccessful();

        self::assertCount(2, $dispatched);
        [[$before, $beforeLevel, $beforeDraftGone], [$published, $publishedLevel, $publishedDraftGone]] = $dispatched->getArrayCopy();

        self::assertInstanceOf(BeforeEntryPublish::class, $before);
        self::assertFalse($before->scheduled);
        // Listeners see the working copy that is published, read in the publish's transaction with the entry locked
        self::assertSame($outside + 1, $beforeLevel);
        self::assertFalse($beforeDraftGone);

        self::assertInstanceOf(EntryPublished::class, $published);
        self::assertSame($outside, $publishedLevel);
        self::assertTrue($publishedDraftGone);
        self::assertSame($page->getUuid(), $published->uuid);
        self::assertSame('page', $published->contentType);
        self::assertSame(['en_US' => 'about-us'], $published->oldSlugs);
        self::assertSame(['en_US' => 'about-us'], $published->newSlugs);
        self::assertFalse($published->scheduled);
        self::assertSame([$page->getUuid()], $published->getAffectedUuids());
    }

    /**
     * @return mixed the decoded answer
     */
    private static function saveHeading(KernelBrowser $client, PageInterface $page, string $text, string $csrfToken): mixed
    {
        return self::json($client, 'PATCH', '/admin/cms/api/entries/' . $page->getUuid(), ['lockToken' => self::lockToken($client, $page), 'changes' => [
            ['op' => 'set_value', 'key' => self::KEY, 'field' => 'text', 'locale' => 'en_US', 'value' => $text],
        ]], $csrfToken);
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function responseData(KernelBrowser $client): array
    {
        $data = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }

    private static function pageManager(): ObjectManager
    {
        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.page');

        return $manager;
    }

    private static function eventDispatcher(): EventDispatcherInterface
    {
        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = self::getContainer()->get('event_dispatcher');

        return $eventDispatcher;
    }
}
