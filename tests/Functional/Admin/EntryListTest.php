<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyManagerInterface;
use Setono\SyliusCMSPlugin\Targeting\Audience;
use Setono\SyliusCMSPlugin\Targeting\CustomerGroupValue;
use Setono\SyliusCMSPlugin\Targeting\DateWindow;
use Setono\SyliusCMSPlugin\Tests\Application\Entity\Announcement;
use Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api\BuilderApiTestCase;
use Setono\SyliusCMSPlugin\Tests\Support\Admins;
use Setono\SyliusCMSPlugin\Tests\Support\Customers;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;

final class EntryListTest extends BuilderApiTestCase
{
    use Admins;
    use Customers;

    private const HEADING = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f';

    /**
     * @test
     */
    public function its_lists_need_an_admin_user(): void
    {
        $client = self::createClient();

        $client->request('GET', '/admin/cms/entries');
        self::assertResponseRedirects('http://localhost/admin/login');

        $client->request('GET', '/admin/cms/entries/page');
        self::assertResponseRedirects('http://localhost/admin/login');
    }

    /**
     * @test
     */
    public function all_entries_lists_every_entry_with_its_title_url_type_status_and_last_edit(): void
    {
        $client = self::signedInClient();
        $about = self::createPage('About us', 'about-us', published: true);
        $announcement = self::createAnnouncement('Free shipping');

        $crawler = $client->request('GET', '/admin/cms/entries');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'All entries');
        self::assertSame(['Title', 'Type', 'Status', 'Last edited', 'Problems', 'Actions'], $crawler->filter('table thead th')->each(static fn (Crawler $th): string => trim($th->text())));
        self::assertSame([$announcement->getUuid(), $about->getUuid()], self::listedUuids($crawler));
        // Creating from All entries opens the create dialog, which starts by picking a content type
        self::assertSame('cms-create-entry', $crawler->selectButton('Create')->attr('aria-controls'));
        self::assertSame(
            [['Announcement', '/admin/cms/entries/announcement/new'], ['Page', '/admin/cms/entries/page/new']],
            $crawler->filter('dialog#cms-create-entry [data-cms-create-entry-type]')->each(static fn (Crawler $type): array => [trim($type->text()), $type->attr('href')]),
        );

        $row = self::row($crawler, $about);
        self::assertSame('About us', $row->filter('[data-column="title"] a')->text());
        // A row opens the entry's overview page
        self::assertSame(sprintf('/admin/cms/entry/%s', $about->getUuid()), $row->filter('[data-column="title"] a')->attr('href'));
        self::assertSame('/en_US/about-us', $row->filter('[data-column="title"] .description')->text());
        self::assertSame('Page', $row->filter('[data-column="type"]')->text());
        self::assertSame(['Published'], self::statusLabels($row));
        self::assertSame('', trim($row->filter('[data-column="problems"]')->text()));

        $row = self::row($crawler, $announcement);
        self::assertSame('Announcement', $row->filter('[data-column="type"]')->text());
        self::assertSame(['Never published'], self::statusLabels($row));
        self::assertCount(0, $row->filter('[data-column="title"] .description'));
    }

    /**
     * @test
     */
    public function a_content_types_list_lists_its_entries_without_their_type(): void
    {
        $client = self::signedInClient();
        $about = self::createPage('About us', 'about-us');
        self::createAnnouncement('Free shipping');

        $crawler = $client->request('GET', '/admin/cms/entries/page');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Pages');
        self::assertSame(['Title', 'Status', 'Last edited', 'Problems', 'Actions'], $crawler->filter('table thead th')->each(static fn (Crawler $th): string => trim($th->text())));
        self::assertSame([$about->getUuid()], self::listedUuids($crawler));
        // Its Create opens the create dialog at the content type's form, which the link leads to without the dialog
        self::assertSame('/admin/cms/entries/page/new', $crawler->selectLink('Create')->attr('href'));
        self::assertSame('cms-create-entry', $crawler->selectLink('Create')->attr('aria-controls'));
        self::assertCount(1, $crawler->filter('dialog#cms-create-entry'));
        self::assertCount(0, $crawler->filter('dialog#cms-create-entry [data-cms-create-entry-type]'));
    }

    /**
     * @test
     */
    public function it_answers_404_for_a_content_type_it_does_not_have(): void
    {
        $client = self::signedInClient();

        $client->request('GET', '/admin/cms/entries/recipe');

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @test
     */
    public function it_labels_the_on_save_settings_that_limit_where_and_to_whom_an_entry_shows(): void
    {
        $client = self::signedInClient();
        self::createCustomerGroup('wholesale', 'Wholesale');
        $page = self::createPage('About us', 'about-us', published: true);
        $page->setEnabled(false);
        $page->setDateWindow(new DateWindow(new \DateTimeImmutable('2026-01-01 08:00:00', new \DateTimeZone('UTC')), new \DateTimeImmutable('2099-01-01 00:00:00', new \DateTimeZone('UTC'))));
        self::channelsAndAudience()->setAudience($page, Audience::only('wholesale', CustomerGroupValue::GUEST));
        $ended = self::createPage('Summer sale', 'summer-sale', published: true);
        $ended->setDateWindow(new DateWindow(null, new \DateTimeImmutable('2026-09-01 00:00:00', new \DateTimeZone('UTC'))));
        self::channelsAndAudience()->setAudience($ended, Audience::except(CustomerGroupValue::NO_GROUP));
        self::flush();

        $crawler = $client->request('GET', '/admin/cms/entries/page');

        self::assertSame(
            ['Published', 'Disabled', sprintf('Shows %s–%s', self::local('2026-01-01 08:00:00'), self::local('2099-01-01 00:00:00')), 'Only Guests, Wholesale'],
            self::statusLabels(self::row($crawler, $page)),
        );
        self::assertSame(
            ['Published', sprintf('Ended %s', self::local('2026-09-01 00:00:00')), 'Everyone except Signed in without a group'],
            self::statusLabels(self::row($crawler, $ended)),
        );
    }

    /**
     * @test
     */
    public function it_shows_a_drafts_changed_title_and_url(): void
    {
        $client = self::signedInClient();
        $page = self::createPage('About us', 'about-us', published: true);
        self::saveDraft($page, 'About our shop', 'about-our-shop');

        $crawler = $client->request('GET', '/admin/cms/entries/page');

        $row = self::row($crawler, $page);
        self::assertSame('About our shop', $row->filter('[data-column="title"] a')->text());
        self::assertSame('/en_US/about-our-shop', $row->filter('[data-column="title"] .description')->text());
        self::assertSame(['Published', 'Unpublished changes'], self::statusLabels($row));
    }

    /**
     * @test
     */
    public function it_shows_the_title_in_the_admin_users_locale(): void
    {
        $client = self::createClient();
        $client->loginUser(self::createAdmin('jensen', 'Jens', 'Jensen', 'da_DK'), 'admin');
        $page = self::createPage('About us', 'about-us');
        self::translate($page, 'da_DK', 'Om os', 'om-os');

        $crawler = $client->request('GET', '/admin/cms/entries/page');

        $row = self::row($crawler, $page);
        self::assertSame('Om os', $row->filter('[data-column="title"] a')->text());
        self::assertSame('/da_DK/om-os', $row->filter('[data-column="title"] .description')->text());
    }

    /**
     * @test
     */
    public function it_shows_when_an_entry_was_last_edited_and_by_whom(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        $csrfToken = self::openBuilder($client, $page, self::createAdmin('jroe', 'Jane', 'Roe'));
        self::json($client, 'PATCH', '/admin/cms/api/entries/' . $page->getUuid(), ['lockToken' => self::lockToken($client, $page), 'changes' => [
            ['op' => 'set_value', 'key' => self::HEADING, 'field' => 'text', 'locale' => 'en_US', 'value' => 'Hello world'],
        ]], $csrfToken);
        self::assertResponseIsSuccessful();
        $lastEditedAt = self::reloadPage($page)->getLastEditedAt()->format('Y-m-d H:i:s');

        $crawler = $client->request('GET', '/admin/cms/entries/page');

        $lastEdited = self::row($crawler, $page)->filter('[data-column="last_edited"]');
        self::assertStringContainsString(self::local($lastEditedAt), $lastEdited->text());
        self::assertStringContainsString('by Jane Roe', $lastEdited->text());
    }

    /**
     * @test
     */
    public function it_filters_on_status(): void
    {
        $client = self::signedInClient();
        $published = self::createPage('Published', 'published', published: true);
        $draft = self::createPage('With a draft', 'with-a-draft', published: true);
        self::saveDraft($draft, 'With a draft', 'with-a-draft');
        $never = self::createPage('Never published', 'never-published');
        $disabled = self::createPage('Disabled', 'disabled', published: true);
        $disabled->setEnabled(false);
        $upcoming = self::createPage('Upcoming', 'upcoming', published: true);
        $upcoming->setDateWindow(new DateWindow(new \DateTimeImmutable('+1 day')));
        $ended = self::createPage('Ended', 'ended', published: true);
        $ended->setDateWindow(new DateWindow(null, new \DateTimeImmutable('-1 second')));
        $current = self::createPage('Current', 'current', published: true);
        $current->setDateWindow(new DateWindow(new \DateTimeImmutable('-1 day'), new \DateTimeImmutable('+1 day')));
        self::flush();

        self::assertSame([$draft->getUuid()], self::listedUuids(self::filter($client, ['status' => 'unpublished_changes'])));
        self::assertSame([$never->getUuid()], self::listedUuids(self::filter($client, ['status' => 'never_published'])));
        self::assertEqualsCanonicalizing(
            [$disabled->getUuid(), $upcoming->getUuid(), $ended->getUuid()],
            self::listedUuids(self::filter($client, ['status' => 'disabled_or_outside_date_window'])),
        );
        self::assertCount(7, self::listedUuids(self::filter($client, ['status' => ''])));
        self::assertNotContains($published->getUuid(), self::listedUuids(self::filter($client, ['status' => 'never_published'])));
        self::assertNotContains($current->getUuid(), self::listedUuids(self::filter($client, ['status' => 'disabled_or_outside_date_window'])));
    }

    /**
     * @test
     */
    public function it_filters_on_channel(): void
    {
        $client = self::signedInClient();
        self::createChannel('B2B', 'Wholesale');
        $everywhere = self::createPage('Everywhere', 'everywhere');
        $web = self::createPage('Web only', 'web-only', channels: ['WEB']);
        $b2b = self::createPage('Wholesale only', 'wholesale-only', channels: ['B2B']);

        self::assertEqualsCanonicalizing([$everywhere->getUuid(), $b2b->getUuid()], self::listedUuids(self::filter($client, ['channel' => 'B2B'])));
        self::assertEqualsCanonicalizing([$everywhere->getUuid(), $web->getUuid()], self::listedUuids(self::filter($client, ['channel' => 'WEB'])));
    }

    /**
     * @test
     */
    public function it_searches_titles_and_slugs_in_any_locale_without_regard_to_case(): void
    {
        $client = self::signedInClient();
        $about = self::createPage('About us', 'about-us');
        $team = self::createPage('Our team', 'people');
        self::translate($team, 'da_DK', 'Vores hold', 'folk');
        self::createPage('Contact', 'contact');

        self::assertSame([$about->getUuid()], self::listedUuids(self::filter($client, ['search' => ['value' => 'ABOUT']])));
        self::assertSame([$team->getUuid()], self::listedUuids(self::filter($client, ['search' => ['value' => 'peop']])));
        // Found by a title in another locale, and listed once with the translations it has
        $crawler = self::filter($client, ['search' => ['value' => 'hold']]);
        self::assertSame([$team->getUuid()], self::listedUuids($crawler));
        self::assertSame('Our team', self::row($crawler, $team)->filter('[data-column="title"] a')->text());
    }

    /**
     * @test
     */
    public function it_keeps_the_filters_in_the_form_and_ignores_a_status_it_does_not_offer(): void
    {
        $client = self::signedInClient();
        self::createPage('About us', 'about-us');

        $crawler = self::filter($client, ['search' => ['value' => 'about'], 'status' => 'never_published']);
        self::assertSame('about', $crawler->filter('#criteria_search_value')->attr('value'));
        self::assertSame('never_published', $crawler->filter('#criteria_status option[selected]')->attr('value'));

        $crawler = self::filter($client, ['status' => 'deleted']);
        self::assertResponseIsSuccessful();
        self::assertCount(1, self::listedUuids($crawler));
    }

    /**
     * @test
     */
    public function it_pages_through_a_long_list(): void
    {
        $client = self::signedInClient();
        for ($i = 1; $i <= 26; ++$i) {
            self::createPage(sprintf('Page %d', $i), sprintf('page-%d', $i));
        }

        self::assertCount(25, self::listedUuids($client->request('GET', '/admin/cms/entries/page')));
        self::assertCount(1, self::listedUuids($client->request('GET', '/admin/cms/entries/page?page=2')));
    }

    private static function signedInClient(): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');

        return $client;
    }

    /**
     * @param array<string, mixed> $criteria what Sylius's grid filter form sends
     */
    private static function filter(KernelBrowser $client, array $criteria): Crawler
    {
        $crawler = $client->request('GET', '/admin/cms/entries/page?' . http_build_query(['criteria' => $criteria]));
        self::assertResponseIsSuccessful();

        return $crawler;
    }

    /**
     * @return list<string>
     */
    private static function listedUuids(Crawler $crawler): array
    {
        return array_values($crawler->filter('table tbody [data-entry]')->each(static fn (Crawler $title): string => (string) $title->attr('data-entry')));
    }

    /**
     * The grid's row of the entry, whose title cell names it
     */
    private static function row(Crawler $crawler, EntryInterface $entry): Crawler
    {
        $row = $crawler->filterXPath(sprintf('//table/tbody/tr[.//*[@data-entry="%s"]]', $entry->getUuid()));
        self::assertCount(1, $row);

        return $row;
    }

    /**
     * @return list<string>
     */
    private static function statusLabels(Crawler $row): array
    {
        return array_values($row->filter('[data-column="status"] .label')->each(static fn (Crawler $label): string => trim($label->text())));
    }

    private static function createAnnouncement(string $title): Announcement
    {
        /** @var FactoryInterface<Announcement> $factory */
        $factory = self::getContainer()->get('app.factory.announcement');
        $announcement = $factory->createNew();
        $announcement->setCurrentLocale('en_US');
        $announcement->setFallbackLocale('en_US');
        $announcement->setTitle($title);
        // Edited after the pages made before it
        $announcement->setLastEdited(new \DateTimeImmutable('+1 minute'), null);

        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('app.manager.announcement');
        $manager->persist($announcement);
        $manager->flush();

        return $announcement;
    }

    private static function translate(PageInterface $page, string $localeCode, string $title, string $slug): void
    {
        $translation = new PageTranslation();
        $translation->setLocale($localeCode);
        $translation->setTitle($title);
        $translation->setSlug($slug);
        $page->addTranslation($translation);
        self::flush();
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

    /**
     * A time in UTC as the admin shows it: in the time zone the shop runs in
     */
    private static function local(string $utc): string
    {
        return (new \DateTimeImmutable($utc, new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i');
    }

    private static function flush(): void
    {
        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.page');
        $manager->flush();
    }
}
