<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Admins;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Every publish stores a revision, which editors preview read-only and restore into the draft.
 * See "Revisions" in docs/spec/publishing.md and the revision_* endpoints in docs/spec/builder.md
 */
final class RevisionsTest extends BuilderApiTestCase
{
    use Admins;

    private const KEY = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f';

    /**
     * @test
     */
    public function two_publishes_give_two_revisions_newest_first_with_who_published_each_and_when(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');

        $csrfToken = self::openBuilder($client, $page);
        self::publish($client, $page, $csrfToken);

        $csrfToken = self::openBuilder($client, $page, self::createAdmin('jroe', 'Jane', 'Roe'));
        self::saveHeading($client, $page, 'Hello world', $csrfToken);
        self::publish($client, $page, $csrfToken);

        $response = self::json($client, 'GET', sprintf('/admin/cms/api/entries/%s/revisions', $page->getUuid()));

        self::assertResponseIsSuccessful();
        self::assertIsArray($response);
        self::assertIsArray($response['revisions'] ?? null);
        self::assertCount(2, $response['revisions']);
        [$second, $first] = $response['revisions'];
        self::assertIsArray($first);
        self::assertIsArray($second);
        self::assertSame(['id', 'publishedAt', 'publishedBy', 'scheduled'], array_keys($second));
        self::assertSame('Jane Roe', $second['publishedBy']);
        self::assertSame('John Doe', $first['publishedBy']);
        self::assertFalse($first['scheduled']);
        self::assertIsInt($first['id']);
        self::assertGreaterThan($first['id'], $second['id']);
        self::assertSame(self::reloadPage($page)->getLastPublishedAt()?->format('Y-m-d\TH:i:s\Z'), $second['publishedAt']);
    }

    /**
     * @test
     */
    public function it_lists_the_revisions_for_an_admin_user_only(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');

        $client->request('GET', sprintf('/admin/cms/api/entries/%s/revisions', $page->getUuid()));
        self::assertResponseRedirects('http://localhost/admin/login');

        $client->loginUser(self::admin(), 'admin');
        $client->request('GET', '/admin/cms/api/entries/0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e40/revisions');
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @test
     */
    public function restoring_a_revision_puts_its_content_in_the_draft_and_leaves_the_shop_as_it_was(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        $csrfToken = self::openBuilder($client, $page);
        self::publish($client, $page, $csrfToken);
        self::saveHeading($client, $page, 'Hello world', $csrfToken);
        self::publish($client, $page, $csrfToken);
        $lockToken = self::lockToken($client, $page);
        [, $first] = self::revisionIds($client, $page);

        $response = self::json($client, 'POST', sprintf('/admin/cms/api/revisions/%d/restore', $first), null, $csrfToken);

        // It answers like entry_show, with the restored content and the lock token of a save
        self::assertResponseIsSuccessful();
        self::assertIsArray($response);
        self::assertSame('unpublished_changes', $response['status'] ?? null);
        self::assertSame($lockToken + 1, $response['lockToken'] ?? null);
        self::assertIsArray($response['entry'] ?? null);
        self::assertSame($page->getUuid(), $response['entry']['uuid'] ?? null);
        self::assertStringContainsString('"text":{"en_US":"Hello"}', (string) self::draftOf($page)?->getContent());
        self::assertSame('John Doe', self::reloadPage($page)->getLastEditedBy());

        // The shop still shows what was published last, until someone publishes the draft
        $client->request('GET', '/en_US/about-us');
        self::assertSelectorTextSame('.cms-heading', 'Hello world');

        self::publish($client, $page, $csrfToken);
        $client->request('GET', '/en_US/about-us');
        self::assertSelectorTextSame('.cms-heading', 'Hello');
        self::assertCount(3, self::revisionIds($client, $page));
    }

    /**
     * @test
     */
    public function restoring_needs_the_builders_csrf_token_and_a_revision_that_exists(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        $csrfToken = self::openBuilder($client, $page);
        self::publish($client, $page, $csrfToken);
        [$revision] = self::revisionIds($client, $page);

        self::json($client, 'POST', sprintf('/admin/cms/api/revisions/%d/restore', $revision), null, 'forged');
        self::assertResponseStatusCodeSame(403);
        self::assertNull(self::draftOf($page));

        self::json($client, 'POST', sprintf('/admin/cms/api/revisions/%d/restore', $revision + 1000), null, $csrfToken);
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @test
     */
    public function a_revisions_preview_renders_its_old_content_at_its_old_url_read_only(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        $csrfToken = self::openBuilder($client, $page);
        self::publish($client, $page, $csrfToken);
        self::json($client, 'PATCH', '/admin/cms/api/entries/' . $page->getUuid(), ['lockToken' => self::lockToken($client, $page), 'changes' => [
            ['op' => 'set_value', 'key' => self::KEY, 'field' => 'text', 'locale' => 'en_US', 'value' => 'Hello world'],
            ['op' => 'set_entry_value', 'field' => 'slug', 'locale' => 'en_US', 'value' => 'about-our-shop'],
        ]], $csrfToken);
        self::assertResponseIsSuccessful();
        self::publish($client, $page, $csrfToken);
        self::saveHeading($client, $page, 'Hello drafts', $csrfToken);
        [, $first] = self::revisionIds($client, $page);

        $response = self::json($client, 'POST', sprintf('/admin/cms/api/revisions/%d/preview', $first), null, $csrfToken);

        self::assertResponseIsSuccessful();
        self::assertIsArray($response);
        self::assertIsString($response['url'] ?? null);
        self::assertStringStartsWith('http://localhost/en_US/about-us?_cms_preview=', $response['url']);

        $client->request('GET', $response['url']);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('.cms-heading', 'Hello');
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex');
        self::assertSelectorNotExists('script[data-cms-bridge]');

        // The shop shows what was published last, and the revision's URL is no longer the page's
        $client->request('GET', '/en_US/about-our-shop');
        self::assertSelectorTextSame('.cms-heading', 'Hello world');
        $client->request('GET', '/en_US/about-us');
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @test
     */
    public function a_revisions_preview_can_be_asked_for_in_a_channel_and_locale_and_says_why_there_is_none(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        $csrfToken = self::openBuilder($client, $page);
        self::publish($client, $page, $csrfToken);
        [$revision] = self::revisionIds($client, $page);

        $response = self::json($client, 'POST', sprintf('/admin/cms/api/revisions/%d/preview', $revision), ['channel' => 'WEB', 'locale' => 'en_US'], $csrfToken);
        self::assertResponseIsSuccessful();
        self::assertIsArray($response);
        self::assertStringStartsWith('http://localhost/en_US/about-us?_cms_preview=', (string) ($response['url'] ?? null));

        $response = self::json($client, 'POST', sprintf('/admin/cms/api/revisions/%d/preview', $revision), ['channel' => 'WEB', 'locale' => 'da_DK'], $csrfToken);
        self::assertResponseStatusCodeSame(400);
        self::assertSame(['error' => 'The channel WEB has no locale da_DK'], $response);

        self::json($client, 'POST', sprintf('/admin/cms/api/revisions/%d/preview', $revision), ['channel' => 1], $csrfToken);
        self::assertResponseStatusCodeSame(400);

        self::json($client, 'POST', sprintf('/admin/cms/api/revisions/%d/preview', $revision), null, 'forged');
        self::assertResponseStatusCodeSame(403);

        self::json($client, 'POST', sprintf('/admin/cms/api/revisions/%d/preview', $revision + 1000), null, $csrfToken);
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * The ids of the page's revisions, newest first, as revision_index lists them
     *
     * @return list<int>
     */
    private static function revisionIds(KernelBrowser $client, PageInterface $page): array
    {
        $response = self::json($client, 'GET', sprintf('/admin/cms/api/entries/%s/revisions', $page->getUuid()));
        self::assertIsArray($response);
        self::assertIsArray($response['revisions'] ?? null);

        $ids = [];
        foreach ($response['revisions'] as $revision) {
            self::assertIsArray($revision);
            self::assertIsInt($revision['id'] ?? null);
            $ids[] = $revision['id'];
        }

        return $ids;
    }

    private static function publish(KernelBrowser $client, PageInterface $page, string $csrfToken): void
    {
        self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/publish', $page->getUuid()), null, $csrfToken);
        self::assertResponseIsSuccessful();
    }

    private static function saveHeading(KernelBrowser $client, PageInterface $page, string $text, string $csrfToken): void
    {
        self::json($client, 'PATCH', '/admin/cms/api/entries/' . $page->getUuid(), ['lockToken' => self::lockToken($client, $page), 'changes' => [
            ['op' => 'set_value', 'key' => self::KEY, 'field' => 'text', 'locale' => 'en_US', 'value' => $text],
        ]], $csrfToken);
        self::assertResponseIsSuccessful();
    }
}
