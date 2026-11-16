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
