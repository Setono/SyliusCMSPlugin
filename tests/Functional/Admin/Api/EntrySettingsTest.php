<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Event\EntrySettingsChanged;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Repository\DraftRepositoryInterface;
use Setono\SyliusCMSPlugin\Targeting\Audience;
use Setono\SyliusCMSPlugin\Targeting\DateWindow;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * The on-save settings take effect on save, outside the draft. See "What takes effect on save" in docs/spec/publishing.md
 * and entry_settings in docs/spec/builder.md#json-endpoints
 */
final class EntrySettingsTest extends BuilderApiTestCase
{
    private const KEY = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f';

    /**
     * @test
     */
    public function it_saves_the_settings_and_answers_with_them(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello', channels: ['WEB']);
        $csrfToken = self::openBuilder($client, $page);

        $response = self::saveSettings($client, $page, [
            'enabled' => false,
            'showFrom' => '2026-11-27T01:00:00+01:00',
            'showUntil' => '2026-12-01T00:00:00.000Z',
            'channels' => ['WEB', 'B2B'],
            'audience' => ['mode' => 'only', 'groups' => ['wholesale', '@guest']],
        ], $csrfToken);

        self::assertResponseIsSuccessful();
        self::assertSame(
            '{"settings":{"enabled":false,"showFrom":"2026-11-27T00:00:00Z","showUntil":"2026-12-01T00:00:00Z","channels":["B2B","WEB"],"audience":{"mode":"only","groups":["@guest","wholesale"]}}}',
            $client->getResponse()->getContent(),
        );
        self::assertIsArray($response);

        $saved = self::reloadPage($page);
        self::assertFalse($saved->isEnabled());
        self::assertTrue($saved->getDateWindow()->equals(new DateWindow(new \DateTimeImmutable('2026-11-27T00:00:00Z'), new \DateTimeImmutable('2026-12-01T00:00:00Z'))));
        self::assertSame(['B2B', 'WEB'], $saved->getChannelCodes());
        self::assertTrue($saved->getAudience()->equals(Audience::only('@guest', 'wholesale')));
    }

    /**
     * @test
     */
    public function it_keeps_the_values_of_settings_left_out(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello', channels: ['WEB']);
        $csrfToken = self::openBuilder($client, $page);
        self::saveSettings($client, $page, ['audience' => ['mode' => 'except', 'groups' => ['retail']], 'showFrom' => '2026-11-27T00:00:00Z'], $csrfToken);

        self::saveSettings($client, $page, ['enabled' => false, 'showUntil' => '2026-12-01T00:00:00Z'], $csrfToken);

        self::assertSame(
            '{"settings":{"enabled":false,"showFrom":"2026-11-27T00:00:00Z","showUntil":"2026-12-01T00:00:00Z","channels":["WEB"],"audience":{"mode":"except","groups":["retail"]}}}',
            $client->getResponse()->getContent(),
        );

        self::saveSettings($client, $page, ['showFrom' => null, 'audience' => ['mode' => 'everyone', 'groups' => []]], $csrfToken);

        self::assertSame(
            '{"settings":{"enabled":false,"showFrom":null,"showUntil":"2026-12-01T00:00:00Z","channels":["WEB"],"audience":{"mode":"everyone","groups":[]}}}',
            $client->getResponse()->getContent(),
        );
    }

    /**
     * @test
     */
    public function it_answers_the_working_copy_with_the_settings(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello', published: true, channels: ['WEB']);
        $csrfToken = self::openBuilder($client, $page);
        self::saveSettings($client, $page, ['audience' => ['mode' => 'only', 'groups' => ['wholesale']]], $csrfToken);

        $response = self::json($client, 'GET', '/admin/cms/api/entries/' . $page->getUuid());

        self::assertIsArray($response);
        self::assertSame(['entry', 'lockToken', 'settings', 'status', 'schedule', 'editors', 'completeness'], array_keys($response));
        self::assertSame(['enabled' => true, 'showFrom' => null, 'showUntil' => null, 'channels' => ['WEB'], 'audience' => ['mode' => 'only', 'groups' => ['wholesale']]], $response['settings']);
        self::assertSame('published', $response['status']);
    }

    /**
     * @test
     */
    public function it_leaves_the_draft_and_the_published_content_alone_when_saving_settings(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);
        $csrfToken = self::openBuilder($client, $page);
        self::json($client, 'PATCH', '/admin/cms/api/entries/' . $page->getUuid(), ['lockToken' => 0, 'changes' => [
            ['op' => 'set_value', 'key' => self::KEY, 'field' => 'text', 'locale' => 'en_US', 'value' => 'Hello world'],
        ]], $csrfToken);
        $rows = self::reloadPage($page)->getBlockTreeJson();
        $draft = self::draftContent($page);
        self::assertNotNull($draft);

        self::saveSettings($client, $page, ['enabled' => false, 'audience' => ['mode' => 'except', 'groups' => ['retail']]], $csrfToken);

        self::assertResponseIsSuccessful();
        self::assertSame($draft, self::draftContent($page));
        self::assertSame($rows, self::reloadPage($page)->getBlockTreeJson());
        self::assertTrue(self::reloadPage($page)->isPublished(), 'Turning enabled off keeps the published content');

        self::json($client, 'GET', '/admin/cms/api/entries/' . $page->getUuid());
        self::assertSame('unpublished_changes', self::responseData($client)['status'] ?? null);
    }

    /**
     * Unpublishing is turning enabled off, without publishing the draft. See "What takes effect on save" in docs/spec/publishing.md
     *
     * @test
     */
    public function it_unpublishes_an_entry_when_enabled_is_turned_off_and_shows_its_published_content_again_when_it_is_turned_on(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);
        $csrfToken = self::openBuilder($client, $page);
        self::json($client, 'PATCH', '/admin/cms/api/entries/' . $page->getUuid(), ['lockToken' => 0, 'changes' => [
            ['op' => 'set_value', 'key' => self::KEY, 'field' => 'text', 'locale' => 'en_US', 'value' => 'Not ready yet'],
        ]], $csrfToken);

        self::saveSettings($client, $page, ['enabled' => false], $csrfToken);
        $client->request('GET', '/en_US/about-us');
        self::assertResponseStatusCodeSame(404);

        self::saveSettings($client, $page, ['enabled' => true], $csrfToken);
        $client->request('GET', '/en_US/about-us');
        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextSame('.cms-heading', 'Hello');
    }

    /**
     * @test
     */
    public function it_bumps_changed_at_when_saving_settings(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        $page->publish(new \DateTimeImmutable('2026-01-01 10:00:00'));
        self::pageManager()->flush();
        $publishedAt = self::reloadPage($page)->getChangedAt();
        $csrfToken = self::openBuilder($client, $page);

        self::saveSettings($client, $page, ['enabled' => false], $csrfToken);

        $saved = self::reloadPage($page);
        self::assertGreaterThan($publishedAt, $saved->getChangedAt());
        self::assertEquals($publishedAt, $saved->getLastPublishedAt());
    }

    /**
     * @test
     */
    public function it_dispatches_entry_settings_changed_after_the_save_commits_with_what_changed(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $page = self::createPage('About us', 'about-us', 'Hello', channels: ['WEB']);
        $csrfToken = self::openBuilder($client, $page);

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $outside = $connection->getTransactionNestingLevel();

        /** @var \ArrayObject<int, array{EntrySettingsChanged, int, bool}> $dispatched the event, the transaction nesting level, and whether the save was in the database */
        $dispatched = new \ArrayObject();
        self::eventDispatcher()->addListener(EntrySettingsChanged::class, static function (EntrySettingsChanged $event) use ($dispatched, $connection, $page): void {
            $enabled = $connection->fetchOne('SELECT enabled FROM setono_sylius_cms__entry WHERE id = ?', [$page->getId()]);
            $dispatched->append([$event, $connection->getTransactionNestingLevel(), in_array($enabled, [0, '0', false], true)]);
        });

        self::saveSettings($client, $page, [
            'enabled' => false,
            'showFrom' => null,
            'channels' => ['WEB'],
            'audience' => ['mode' => 'only', 'groups' => ['wholesale']],
        ], $csrfToken);

        self::assertCount(1, $dispatched);
        [[$event, $level, $saved]] = $dispatched->getArrayCopy();
        self::assertSame($page->getUuid(), $event->uuid);
        self::assertSame(['enabled', 'audience'], $event->changed, 'The date window and the channels kept their values');
        self::assertSame($outside, $level);
        self::assertTrue($saved);

        self::saveSettings($client, $page, ['showUntil' => '2026-12-01T00:00:00Z', 'channels' => ['B2B', 'WEB']], $csrfToken);

        self::assertCount(2, $dispatched);
        [, [$event]] = $dispatched->getArrayCopy();
        self::assertSame(['date_window', 'channels'], $event->changed);
    }

    /**
     * @test
     */
    public function it_changes_nothing_when_saving_the_settings_it_already_has(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $page = self::createPage('About us', 'about-us', 'Hello', published: true, channels: ['WEB']);
        $changedAt = self::reloadPage($page)->getChangedAt();
        $csrfToken = self::openBuilder($client, $page);
        $dispatched = new \ArrayObject();
        self::eventDispatcher()->addListener(EntrySettingsChanged::class, static fn (EntrySettingsChanged $event) => $dispatched->append($event));

        self::saveSettings($client, $page, ['enabled' => true, 'channels' => ['WEB'], 'audience' => ['mode' => 'everyone', 'groups' => []]], $csrfToken);

        self::assertResponseIsSuccessful();
        self::assertCount(0, $dispatched);
        self::assertEquals($changedAt, self::reloadPage($page)->getChangedAt());
    }

    /**
     * @test
     *
     * @dataProvider invalidSettings
     *
     * @param array<array-key, mixed> $settings
     */
    public function it_refuses_settings_it_cannot_save(array $settings, string $error): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello', channels: ['WEB']);
        $csrfToken = self::openBuilder($client, $page);

        $response = self::saveSettings($client, $page, $settings, $csrfToken);

        self::assertResponseStatusCodeSame(400);
        self::assertSame(['error' => $error], $response);
        self::assertSame(['WEB'], self::reloadPage($page)->getChannelCodes());
        self::assertTrue(self::reloadPage($page)->isEnabled());
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function invalidSettings(): iterable
    {
        yield 'enabled is not a boolean' => [['enabled' => 'yes', 'channels' => []], '"enabled" must be true or false'];
        yield 'an end before the start' => [['showFrom' => '2026-12-01T00:00:00Z', 'showUntil' => '2026-11-27T00:00:00Z'], '"Show until" (2026-11-27T00:00:00Z) must come after "Show from" (2026-12-01T00:00:00Z)'];
        yield 'a date that is no date' => [['showFrom' => 'tomorrow'], '"showFrom" must be a date and time such as 2026-11-27T00:00:00Z, or null'];
        yield 'channels that are no list of codes' => [['channels' => ['WEB', 7]], '"channels" must be a list of channel codes'];
        yield 'an unknown audience mode' => [['audience' => ['mode' => 'some', 'groups' => []]], '"audience" must have a "mode" of everyone, only or except, and "groups" as a list of customer group values'];
        yield 'an audience group that is no customer group value' => [['audience' => ['mode' => 'only', 'groups' => ['@admin']]], '"@admin" is neither a customer group code, "@guest" nor "@no_group"'];
        yield 'groups for everyone' => [['audience' => ['mode' => 'everyone', 'groups' => ['wholesale']]], 'An audience of everyone has no groups'];
        yield 'no object' => [['WEB'], 'The settings must be a JSON object'];
    }

    /**
     * @test
     */
    public function it_needs_a_valid_csrf_token(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        self::openBuilder($client, $page);

        self::saveSettings($client, $page, ['enabled' => false], 'not-the-token');

        self::assertResponseStatusCodeSame(403);
        self::assertTrue(self::reloadPage($page)->isEnabled());
    }

    /**
     * @test
     */
    public function it_needs_an_admin_user(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');

        self::saveSettings($client, $page, ['enabled' => false]);

        self::assertResponseRedirects('http://localhost/admin/login');
    }

    /**
     * @test
     */
    public function it_answers_404_for_an_unknown_entry(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        $csrfToken = self::openBuilder($client, $page);

        self::json($client, 'PUT', '/admin/cms/api/entries/0193a5be-0000-7000-8000-000000000000/settings', ['enabled' => false], $csrfToken);

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @param array<array-key, mixed> $settings
     */
    private static function saveSettings(KernelBrowser $client, PageInterface $page, array $settings, ?string $csrfToken = null): mixed
    {
        return self::json($client, 'PUT', sprintf('/admin/cms/api/entries/%s/settings', $page->getUuid()), $settings, $csrfToken);
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

    private static function draftContent(PageInterface $page): ?string
    {
        self::pageManager()->clear();

        /** @var DraftRepositoryInterface $drafts */
        $drafts = self::getContainer()->get('setono_sylius_cms.repository.draft');

        return $drafts->findOneByEntry(self::reloadPage($page))?->getContent();
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
