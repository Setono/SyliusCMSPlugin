<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Event\BeforeEntryPublish;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyManagerInterface;
use Setono\SyliusCMSPlugin\Tests\Application\Entity\Announcement;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * The entry overview page, between an entry's list and the builder. See "Entry overview page" in docs/spec/admin.md
 */
final class EntryOverviewTest extends WebTestCase
{
    use Entries;

    /**
     * @test
     */
    public function it_needs_an_admin_user(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us');

        $client->request('GET', sprintf('/admin/cms/entry/%s', $page->getUuid()));

        self::assertResponseRedirects('http://localhost/admin/login');
    }

    /**
     * @test
     */
    public function it_answers_404_for_an_entry_it_does_not_have(): void
    {
        $client = self::signedInClient();

        $client->request('GET', '/admin/cms/entry/0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f');

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @test
     */
    public function its_header_shows_the_title_content_type_url_and_status_labels_with_open_in_builder(): void
    {
        $client = self::signedInClient();
        $page = self::createPage('About us', 'about-us', published: true);

        $crawler = $client->request('GET', sprintf('/admin/cms/entry/%s', $page->getUuid()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('title', 'About us');
        $header = $crawler->filter('[data-overview-header]');
        self::assertSame('About us', $header->filter('h1 [data-title]')->text());
        self::assertSame('Page', $header->filter('h1 [data-content-type]')->text());
        self::assertSame('/en_US/about-us', $header->filter('h1 [data-url]')->text());
        self::assertSame(['Published'], $header->filter('[data-status] .label')->each(static fn (Crawler $label): string => trim($label->text())));
        self::assertSame(sprintf('/admin/cms/entry/%s/builder', $page->getUuid()), $header->selectLink('Open in builder')->attr('href'));

        // The breadcrumb leads back to the entry's list
        self::assertSame('/admin/cms/entries/page', $crawler->filter('.breadcrumb')->selectLink('Pages')->attr('href'));
    }

    /**
     * @test
     */
    public function its_header_shows_the_working_copys_title(): void
    {
        $client = self::signedInClient();
        $page = self::createPage('About us', 'about-us', published: true);
        self::saveDraft($page, 'About our shop', 'about-our-shop');

        $crawler = $client->request('GET', sprintf('/admin/cms/entry/%s', $page->getUuid()));

        self::assertSame('About our shop', $crawler->filter('[data-overview-header] h1 [data-title]')->text());
        self::assertSame(['Published', 'Unpublished changes'], $crawler->filter('[data-overview-header] [data-status] .label')->each(static fn (Crawler $label): string => trim($label->text())));
    }

    /**
     * @test
     */
    public function its_publishing_section_offers_to_publish_an_entry_never_published(): void
    {
        $client = self::signedInClient();
        $page = self::createPage('About us', 'about-us');

        $crawler = $client->request('GET', sprintf('/admin/cms/entry/%s', $page->getUuid()));

        $publishing = $crawler->filter('[data-section="publishing"]');
        self::assertSame('Publishing', $publishing->filter('h2')->text());
        self::assertSame('never_published', $publishing->filter('[data-publishing-state]')->attr('data-publishing-state'));
        self::assertStringContainsString('Never published', $publishing->filter('[data-publishing-state]')->text());
        self::assertCount(1, $publishing->selectButton('Publish'));
        self::assertCount(0, $publishing->selectButton('Discard changes'));
    }

    /**
     * @test
     */
    public function its_publishing_section_offers_to_publish_or_discard_unpublished_changes(): void
    {
        $client = self::signedInClient();
        $page = self::createPage('About us', 'about-us', published: true);
        self::saveDraft($page, 'About our shop', 'about-us');

        $crawler = $client->request('GET', sprintf('/admin/cms/entry/%s', $page->getUuid()));

        $publishing = $crawler->filter('[data-section="publishing"]');
        self::assertSame('unpublished_changes', $publishing->filter('[data-publishing-state]')->attr('data-publishing-state'));
        self::assertStringContainsString('Unpublished changes', $publishing->filter('[data-publishing-state]')->text());
        self::assertCount(1, $publishing->filter('[data-last-published]'));
        self::assertCount(1, $publishing->selectButton('Publish'));
        self::assertCount(1, $publishing->selectButton('Discard changes'));
    }

    /**
     * @test
     */
    public function its_publishing_section_has_nothing_to_publish_for_a_published_entry_without_changes(): void
    {
        $client = self::signedInClient();
        $page = self::createPage('About us', 'about-us', published: true);

        $crawler = $client->request('GET', sprintf('/admin/cms/entry/%s', $page->getUuid()));

        $publishing = $crawler->filter('[data-section="publishing"]');
        self::assertSame('published', $publishing->filter('[data-publishing-state]')->attr('data-publishing-state'));
        self::assertCount(0, $publishing->selectButton('Publish'));
        self::assertCount(0, $publishing->selectButton('Discard changes'));
    }

    /**
     * @test
     */
    public function it_publishes_the_working_copy_and_comes_back_to_the_overview_page(): void
    {
        $client = self::signedInClient();
        $page = self::createPage('About us', 'about-us', published: true);
        self::saveDraft($page, 'About our shop', 'about-our-shop');
        $overview = sprintf('/admin/cms/entry/%s', $page->getUuid());

        $client->request('GET', $overview);
        $client->submitForm('Publish');

        self::assertResponseRedirects($overview);
        $reloaded = self::reloadPage($page);
        self::assertSame('About our shop', $reloaded->getTranslation('en_US')->getTitle());
        self::assertNull(self::draftOf($page));

        $crawler = $client->followRedirect();
        self::assertSelectorTextContains('.sylius-flash-message', 'The entry has been published.');
        self::assertSame('published', $crawler->filter('[data-publishing-state]')->attr('data-publishing-state'));
    }

    /**
     * @test
     */
    public function it_publishes_an_entry_never_published(): void
    {
        $client = self::signedInClient();
        $page = self::createPage('About us', 'about-us', 'Hello');

        $client->request('GET', sprintf('/admin/cms/entry/%s', $page->getUuid()));
        $client->submitForm('Publish');

        self::assertTrue(self::reloadPage($page)->isPublished());

        $client->request('GET', '/en_US/about-us');
        self::assertSelectorTextSame('.cms-heading', 'Hello');
    }

    /**
     * @test
     */
    public function a_publish_that_validation_refuses_says_how_many_errors_need_fixing(): void
    {
        $client = self::signedInClient();
        $page = self::createPageWithBlocks('About us', 'about-us', [new StoredBlock('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f', 'heading', 1)]);
        $overview = sprintf('/admin/cms/entry/%s', $page->getUuid());

        $client->request('GET', $overview);
        $client->submitForm('Publish');

        self::assertResponseRedirects($overview);
        self::assertFalse(self::reloadPage($page)->isPublished());

        $client->followRedirect();
        self::assertSelectorTextContains('.sylius-flash-message.negative', 'It wasn\'t published: one error needs fixing.');
    }

    /**
     * @test
     */
    public function a_vetoed_publish_shows_the_listeners_message(): void
    {
        $client = self::signedInClient();
        $client->disableReboot();
        $page = self::createPage('About us', 'about-us');

        self::eventDispatcher()->addListener(BeforeEntryPublish::class, static function (BeforeEntryPublish $event): void {
            $event->veto('It has to wait for Monday');
        });

        $client->request('GET', sprintf('/admin/cms/entry/%s', $page->getUuid()));
        $client->submitForm('Publish');

        self::assertFalse(self::reloadPage($page)->isPublished());

        $client->followRedirect();
        self::assertSelectorTextContains('.sylius-flash-message.negative', 'It has to wait for Monday');
    }

    /**
     * @test
     */
    public function it_discards_the_draft_and_comes_back_to_the_overview_page(): void
    {
        $client = self::signedInClient();
        $page = self::createPage('About us', 'about-us', published: true);
        self::saveDraft($page, 'About our shop', 'about-our-shop');
        $overview = sprintf('/admin/cms/entry/%s', $page->getUuid());

        $client->request('GET', $overview);
        $client->submitForm('Discard changes');

        self::assertResponseRedirects($overview);
        self::assertNull(self::draftOf($page));
        self::assertSame('About us', self::reloadPage($page)->getTranslation('en_US')->getTitle());

        $crawler = $client->followRedirect();
        self::assertSelectorTextContains('.sylius-flash-message', 'The unpublished changes have been discarded.');
        self::assertSame('About us', $crawler->filter('[data-overview-header] h1 [data-title]')->text());
    }

    /**
     * @test
     */
    public function publish_and_discard_need_the_pages_csrf_token(): void
    {
        $client = self::signedInClient();
        $page = self::createPage('About us', 'about-us', published: true);
        self::saveDraft($page, 'About our shop', 'about-us');

        $client->request('POST', sprintf('/admin/cms/entry/%s/publish', $page->getUuid()), ['_csrf_token' => 'forged']);
        self::assertResponseStatusCodeSame(403);

        $client->request('POST', sprintf('/admin/cms/entry/%s/discard', $page->getUuid()), ['_csrf_token' => 'forged']);
        self::assertResponseStatusCodeSame(403);

        self::assertNotNull(self::draftOf($page));
    }

    /**
     * @test
     */
    public function publish_and_discard_need_an_admin_user(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us');

        $client->request('POST', sprintf('/admin/cms/entry/%s/publish', $page->getUuid()));
        self::assertResponseRedirects('http://localhost/admin/login');

        $client->request('POST', sprintf('/admin/cms/entry/%s/discard', $page->getUuid()));
        self::assertResponseRedirects('http://localhost/admin/login');
    }

    /**
     * @test
     */
    public function its_urls_section_shows_the_url_per_locale(): void
    {
        $client = self::signedInClient();
        $page = self::createPage('About us', 'about-us', published: true);
        self::translate($page, 'da_DK', 'Om os', 'om-os');

        $crawler = $client->request('GET', sprintf('/admin/cms/entry/%s', $page->getUuid()));

        $urls = $crawler->filter('[data-section="urls"]');
        self::assertSame('URLs', $urls->filter('h2')->text());
        self::assertSame([
            ['da_DK', 'Danish (Denmark)', '/da_DK/om-os'],
            ['en_US', 'English (United States)', '/en_US/about-us'],
        ], $urls->filter('[data-locale]')->each(static fn (Crawler $row): array => [
            $row->attr('data-locale'),
            $row->filter('[data-locale-name]')->text(),
            $row->filter('[data-url]')->text(),
        ]));
    }

    /**
     * @test
     */
    public function its_urls_section_shows_a_url_the_draft_changes_beside_the_published_one(): void
    {
        $client = self::signedInClient();
        $page = self::createPage('About us', 'about-us', published: true);
        self::saveDraft($page, 'About us', 'about-our-shop');

        $crawler = $client->request('GET', sprintf('/admin/cms/entry/%s', $page->getUuid()));

        $row = $crawler->filter('[data-section="urls"] [data-locale="en_US"]');
        self::assertSame('/en_US/about-us', $row->filter('[data-url]')->text());
        self::assertStringContainsString('/en_US/about-our-shop', $row->filter('[data-draft-url]')->text());
    }

    /**
     * @test
     */
    public function its_urls_section_lists_the_old_slugs_that_redirect_to_a_url(): void
    {
        $client = self::signedInClient();
        $page = self::createPage('About us', 'about', published: true);
        foreach (['about-us', 'about-our-shop'] as $slug) {
            self::saveChanges($page, [['op' => 'set_entry_value', 'field' => 'slug', 'locale' => 'en_US', 'value' => $slug]]);
            self::publishEntry($page);
        }

        $crawler = $client->request('GET', sprintf('/admin/cms/entry/%s', $page->getUuid()));

        $row = $crawler->filter('[data-section="urls"] [data-locale="en_US"]');
        self::assertSame('/en_US/about-our-shop', $row->filter('[data-url]')->text());
        self::assertSame(['/en_US/about-us', '/en_US/about'], $row->filter('[data-old-url]')->each(static fn (Crawler $url): string => $url->text()));
        self::assertStringStartsWith('Redirects from:', $row->filter('[data-old-urls]')->text());
    }

    /**
     * @test
     */
    public function its_translations_section_shows_a_chip_per_locale_of_the_entrys_channels_from_its_draft(): void
    {
        $client = self::signedInClient();
        self::addLocales('WEB', 'da_DK');
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);
        self::translate($page, 'da_DK', 'Om os', 'om-os');
        self::saveDraft($page, 'About our shop', 'about-us');

        $crawler = $client->request('GET', sprintf('/admin/cms/entry/%s', $page->getUuid()));

        $translations = $crawler->filter('[data-section="translations"]');
        self::assertSame('Translations', $translations->filter('h2')->text());
        self::assertSame(
            ['en_US complete', 'da_DK 1 untranslated'],
            $translations->filter('[data-translation-chip]')->each(static fn (Crawler $chip): string => (string) preg_replace('/\s+/', ' ', trim($chip->text()))),
        );
    }

    /**
     * @test
     */
    public function an_entry_in_no_channel_has_no_translations_section(): void
    {
        $client = self::signedInClient();
        $page = self::createPage('About us', 'about-us', channels: []);

        $crawler = $client->request('GET', sprintf('/admin/cms/entry/%s', $page->getUuid()));

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('[data-section="translations"]'));
    }

    /**
     * @test
     */
    public function an_entry_without_a_url_of_its_own_has_no_urls_section(): void
    {
        $client = self::signedInClient();
        /** @var FactoryInterface<Announcement> $factory */
        $factory = self::getContainer()->get('app.factory.announcement');
        $announcement = $factory->createNew();
        $announcement->setCurrentLocale('en_US');
        $announcement->setFallbackLocale('en_US');
        $announcement->setTitle('Free shipping');
        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('app.manager.announcement');
        $manager->persist($announcement);
        $manager->flush();

        $crawler = $client->request('GET', sprintf('/admin/cms/entry/%s', $announcement->getUuid()));

        self::assertResponseIsSuccessful();
        self::assertSame('Announcement', $crawler->filter('[data-overview-header] h1 [data-content-type]')->text());
        self::assertCount(0, $crawler->filter('[data-overview-header] h1 [data-url]'));
        self::assertCount(1, $crawler->filter('[data-section="publishing"]'));
        self::assertCount(0, $crawler->filter('[data-section="urls"]'));
    }

    private static function signedInClient(): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');

        return $client;
    }

    /**
     * Saves a draft of the published page with this title and slug, as the builder's autosave would
     */
    private static function saveDraft(PageInterface $page, string $title, string $slug): void
    {
        /** @var WorkingCopyManagerInterface $workingCopies */
        $workingCopies = self::getContainer()->get(WorkingCopyManagerInterface::class);
        $workingCopy = $workingCopies->getWorkingCopy($page);
        $workingCopy->setTitle($title);
        $workingCopy->setSlug($slug);
        $workingCopies->save($page, $workingCopy);
    }

    private static function translate(PageInterface $page, string $localeCode, string $title, string $slug): void
    {
        $translation = new PageTranslation();
        $translation->setLocale($localeCode);
        $translation->setTitle($title);
        $translation->setSlug($slug);
        $page->addTranslation($translation);

        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.page');
        $manager->flush();
    }

    private static function eventDispatcher(): EventDispatcherInterface
    {
        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = self::getContainer()->get('event_dispatcher');

        return $eventDispatcher;
    }
}
