<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\Revisions;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The Revisions section of the entry overview page: who published each revision and when, with a read-only preview
 * and a restore into the draft. See "Entry overview page" in docs/spec/admin.md and "Revisions" in docs/spec/publishing.md
 */
final class EntryRevisionsTest extends WebTestCase
{
    use Entries;
    use Revisions;

    private const KEY = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f';

    /**
     * @test
     */
    public function it_lists_who_published_each_revision_and_when_newest_first(): void
    {
        $client = self::signedInClient();
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);
        self::storeRevision($page, [self::heading(self::KEY, 'Hello')], '2026-11-01 10:00:00', 'John Doe');
        $scheduled = self::storeRevision($page, [self::heading(self::KEY, 'Hello world')], '2026-11-14 08:30:00', 'Jane Roe');
        $scheduled->setScheduled(true);
        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.revision');
        $manager->flush();

        $crawler = $client->request('GET', sprintf('/admin/cms/entry/%s', $page->getUuid()));

        $section = $crawler->filter('[data-section="revisions"]');
        self::assertSame('Revisions', $section->filter('h2')->text());
        self::assertSame([
            ['2026-11-14 08:30', 'Jane Roe', true],
            ['2026-11-01 10:00', 'John Doe', false],
        ], $section->filter('[data-revision]')->each(static fn (Crawler $row): array => [
            $row->filter('[data-published-at]')->text(),
            $row->filter('[data-published-by]')->text(),
            1 === $row->filter('[data-scheduled]')->count(),
        ]));

        // Each can be previewed and restored
        self::assertCount(2, $section->selectLink('Preview'));
        self::assertCount(2, $section->selectButton('Restore'));
    }

    /**
     * @test
     */
    public function it_says_every_publish_stores_one_before_the_first(): void
    {
        $client = self::signedInClient();
        $page = self::createPage('About us', 'about-us', 'Hello');

        $crawler = $client->request('GET', sprintf('/admin/cms/entry/%s', $page->getUuid()));

        $section = $crawler->filter('[data-section="revisions"]');
        self::assertStringContainsString('Every publish stores a revision', $section->text());
        self::assertCount(0, $section->filter('[data-revision]'));
    }

    /**
     * @test
     */
    public function its_preview_opens_the_revisions_read_only_preview_in_the_shop(): void
    {
        $client = self::signedInClient();
        $page = self::createPage('About us', 'about-us', 'Hello world', published: true);
        self::storeRevision($page, [self::heading(self::KEY, 'Hello')]);

        $crawler = $client->request('GET', sprintf('/admin/cms/entry/%s', $page->getUuid()));
        $preview = $crawler->filter('[data-section="revisions"]')->selectLink('Preview');
        self::assertSame('_blank', $preview->attr('target'));
        $client->click($preview->link());

        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertStringStartsWith('http://localhost/en_US/about-us?_cms_preview=', $location);
        $client->request('GET', $location);
        self::assertSelectorTextSame('.cms-heading', 'Hello');
        self::assertSelectorNotExists('script[data-cms-bridge]');
    }

    /**
     * @test
     */
    public function its_preview_comes_back_and_says_why_when_the_revision_has_no_page_to_show(): void
    {
        $client = self::signedInClient();
        $page = self::createPage('About us', 'about-us', 'Hello', published: true, channels: []);
        $revision = self::storeRevision($page, [self::heading(self::KEY, 'Hello')]);
        $overview = sprintf('/admin/cms/entry/%s', $page->getUuid());

        $client->request('GET', sprintf('%s/revisions/%d/preview', $overview, (int) $revision->getId()));

        self::assertResponseRedirects($overview);
        $client->followRedirect();
        self::assertSelectorTextContains('.sylius-flash-message.negative', 'The entry isn\'t in any channel');
    }

    /**
     * @test
     */
    public function it_restores_a_revision_into_the_draft_and_comes_back_to_the_overview_page(): void
    {
        $client = self::signedInClient();
        $page = self::createPage('About us', 'about-us', 'Hello world', published: true);
        self::storeRevision($page, [self::heading(self::KEY, 'Hello')]);
        $overview = sprintf('/admin/cms/entry/%s', $page->getUuid());

        $client->request('GET', $overview);
        $client->submitForm('Restore');

        self::assertResponseRedirects($overview);
        self::assertStringContainsString('"text":{"en_US":"Hello"}', (string) self::draftOf($page)?->getContent());

        $crawler = $client->followRedirect();
        self::assertSelectorTextContains('.sylius-flash-message', 'The revision has been restored into the draft.');
        self::assertSame('unpublished_changes', $crawler->filter('[data-publishing-state]')->attr('data-publishing-state'));

        // The shop shows what was published, until someone publishes the draft
        $client->request('GET', '/en_US/about-us');
        self::assertSelectorTextSame('.cms-heading', 'Hello world');
    }

    /**
     * @test
     */
    public function restoring_needs_the_pages_csrf_token_and_a_revision_of_the_entry(): void
    {
        $client = self::signedInClient();
        $page = self::createPage('About us', 'about-us', 'Hello world', published: true);
        $other = self::createPage('Contact', 'contact', 'Hi', published: true);
        $revision = self::storeRevision($page, [self::heading(self::KEY, 'Hello')]);
        $ofOther = self::storeRevision($other, [self::heading(self::KEY, 'Hi there')]);

        $client->request('POST', sprintf('/admin/cms/entry/%s/revisions/%d/restore', $page->getUuid(), (int) $revision->getId()), ['_csrf_token' => 'forged']);
        self::assertResponseStatusCodeSame(403);

        $crawler = $client->request('GET', sprintf('/admin/cms/entry/%s', $page->getUuid()));
        $token = $crawler->filter('[data-section="revisions"] input[name="_csrf_token"]')->attr('value');
        $client->request('POST', sprintf('/admin/cms/entry/%s/revisions/%d/restore', $page->getUuid(), (int) $ofOther->getId()), ['_csrf_token' => $token]);
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', sprintf('/admin/cms/entry/%s/revisions/%d/preview', $page->getUuid(), (int) $ofOther->getId()));
        self::assertResponseStatusCodeSame(404);

        self::assertNull(self::draftOf($page));
        self::assertNull(self::draftOf($other));
    }

    /**
     * @test
     */
    public function preview_and_restore_need_an_admin_user(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);
        $revision = self::storeRevision($page, [self::heading(self::KEY, 'Hello')]);

        $client->request('GET', sprintf('/admin/cms/entry/%s/revisions/%d/preview', $page->getUuid(), (int) $revision->getId()));
        self::assertResponseRedirects('http://localhost/admin/login');

        $client->request('POST', sprintf('/admin/cms/entry/%s/revisions/%d/restore', $page->getUuid(), (int) $revision->getId()));
        self::assertResponseRedirects('http://localhost/admin/login');
    }

    private static function signedInClient(): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');

        return $client;
    }
}
