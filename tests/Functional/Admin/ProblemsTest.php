<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The errors and warnings the admin flags: on the entry's overview page, by the lists' problem marker and their Needs
 * attention filter, and in the Content badge. See "Problems" in docs/spec/admin.md
 */
final class ProblemsTest extends WebTestCase
{
    use Entries;

    private const HEADING = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f';

    private const PROMO = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e50';

    /**
     * @test
     */
    public function the_overview_page_lists_the_entrys_problems_errors_first_each_with_where_it_is_fixed(): void
    {
        $client = self::signedInClient();
        self::addLocales('WEB', 'da_DK');
        $page = self::pageWithProblems();

        $crawler = $client->request('GET', sprintf('/admin/cms/entry/%s', $page->getUuid()));

        self::assertResponseIsSuccessful();
        $section = $crawler->filter('[data-section="problems"]');
        self::assertSame('Problems', $section->filter('h2')->text());
        $problems = $section->filter('[data-problem]');
        self::assertCount(2, $problems);

        $misfit = $problems->eq(0);
        self::assertSame(['misfit_value', 'error', self::HEADING], [$misfit->attr('data-problem'), $misfit->attr('data-severity'), $misfit->attr('data-block')]);
        self::assertSame('Error · Danish (Denmark)', $misfit->filter('.header')->text());
        self::assertSame('Text in the Heading block holds a value that doesn\'t fit its field type, so it counts as empty.', $misfit->filter('.description')->text());
        self::assertSame(sprintf('/admin/cms/entry/%s/builder?block=%s&locale=da_DK', $page->getUuid(), self::HEADING), $misfit->selectLink('Fix in builder')->attr('href'));

        $unknown = $problems->eq(1);
        self::assertSame(['unknown_block_type', 'warning', self::PROMO], [$unknown->attr('data-problem'), $unknown->attr('data-severity'), $unknown->attr('data-block')]);
        self::assertSame('Warning', $unknown->filter('.header')->text());
        self::assertSame('A block of the type "promo", which the code no longer has, isn\'t shown.', $unknown->filter('.description')->text());
        self::assertSame(sprintf('/admin/cms/entry/%s/builder?block=%s', $page->getUuid(), self::PROMO), $unknown->selectLink('Fix in builder')->attr('href'));

        // It sits right after the Publishing section
        $sections = $crawler->filter('[data-section]')->each(static fn (Crawler $section): ?string => $section->attr('data-section'));
        self::assertSame(['publishing', 'problems'], array_slice($sections, 0, 2));
    }

    /**
     * See "Routes added later" in docs/spec/urls-and-seo.md
     *
     * @test
     */
    public function the_overview_page_warns_of_a_published_url_a_sylius_route_takes(): void
    {
        $client = self::signedInClient();
        // Published without the checks, as if the route was added after the page was published
        $page = self::createPage('Log in', 'login', 'Hello', published: true);

        $crawler = $client->request('GET', sprintf('/admin/cms/entry/%s', $page->getUuid()));

        $problem = $crawler->filter('[data-section="problems"] [data-problem]');
        self::assertCount(1, $problem);
        self::assertSame(['url_taken_by_route', 'warning'], [$problem->attr('data-problem'), $problem->attr('data-severity')]);
        self::assertSame('URL taken by a Sylius route: shoppers get the page of the route sylius_shop_login at /en_US/login.', $problem->filter('.description')->text());
        self::assertSame(sprintf('/admin/cms/entry/%s/builder?locale=en_US', $page->getUuid()), $problem->selectLink('Fix in builder')->attr('href'));
    }

    /**
     * @test
     */
    public function an_entry_whose_layout_was_removed_from_code_has_a_warning_fixed_in_the_builders_entry_settings(): void
    {
        $client = self::signedInClient();
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);
        $page->setBlockTree(new StoredTree(new StoredLayout('gone', 1), $page->getBlockTree()->blocks));
        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.page');
        $manager->flush();

        $crawler = $client->request('GET', sprintf('/admin/cms/entry/%s', $page->getUuid()));

        self::assertResponseIsSuccessful();
        $problem = $crawler->filter('[data-section="problems"] [data-problem]');
        self::assertCount(1, $problem);
        self::assertSame(['removed_layout', 'warning'], [$problem->attr('data-problem'), $problem->attr('data-severity')]);
        self::assertSame('Its layout "gone" was removed from the code or disabled, so it shows in the default layout, Standard, until you pick one.', $problem->filter('.description')->text());
        self::assertSame(sprintf('/admin/cms/entry/%s/builder', $page->getUuid()), $problem->selectLink('Fix in builder')->attr('href'));

        // The shop shows it in the default layout meanwhile
        $client->request('GET', '/en_US/about-us');
        self::assertSelectorTextSame('h1', 'About us');
    }

    /**
     * @test
     */
    public function an_entry_without_problems_has_no_problems_section_and_a_missing_translation_is_no_problem(): void
    {
        $client = self::signedInClient();
        self::addLocales('WEB', 'da_DK');
        $page = self::createPage('About us', 'about-us', 'Hello');

        $crawler = $client->request('GET', sprintf('/admin/cms/entry/%s', $page->getUuid()));

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('[data-section="problems"]'));
    }

    /**
     * @test
     */
    public function the_lists_mark_an_entry_with_a_problem_and_link_the_marker_to_its_problems(): void
    {
        $client = self::signedInClient();
        self::addLocales('WEB', 'da_DK');
        $errors = self::pageWithProblems();
        $warnings = self::createPageWithBlocks('Contact', 'contact', [new StoredBlock(self::PROMO, 'promo', 1)]);
        // Missing translations are never problems
        $clean = self::createPage('Clean', 'clean', 'Hello', published: true);

        foreach (['/admin/cms/entries/page', '/admin/cms/entries'] as $list) {
            $crawler = $client->request('GET', $list);
            self::assertResponseIsSuccessful();

            $marker = self::row($crawler, $errors)->filter('[data-column="problems"] a');
            self::assertSame('error', $marker->attr('data-problems'));
            self::assertSame('2', trim($marker->text()));
            self::assertSame('1 error, 1 warning', $marker->attr('title'));
            self::assertSame(sprintf('/admin/cms/entry/%s#cms-section-problems', $errors->getUuid()), $marker->attr('href'));

            $marker = self::row($crawler, $warnings)->filter('[data-column="problems"] a');
            self::assertSame('warning', $marker->attr('data-problems'));
            self::assertSame('1', trim($marker->text()));
            self::assertSame('No errors, 1 warning', $marker->attr('title'));

            self::assertSame('', trim(self::row($crawler, $clean)->filter('[data-column="problems"]')->text()));
        }
    }

    /**
     * @test
     */
    public function the_needs_attention_filter_lists_the_entries_with_a_problem(): void
    {
        $client = self::signedInClient();
        $errors = self::pageWithProblems();
        $warnings = self::createPageWithBlocks('Contact', 'contact', [new StoredBlock(self::PROMO, 'promo', 1)]);
        self::createPage('Clean', 'clean', 'Hello', published: true);

        $crawler = $client->request('GET', '/admin/cms/entries/page?' . http_build_query(['criteria' => ['status' => 'needs_attention']]));

        self::assertResponseIsSuccessful();
        self::assertEqualsCanonicalizing([$errors->getUuid(), $warnings->getUuid()], self::listedUuids($crawler));
        self::assertSame('Needs attention', $crawler->filter('#criteria_status option')->eq(1)->text(), 'It\'s the first status the filter offers');

        $crawler = $client->request('GET', '/admin/cms/entries?' . http_build_query(['criteria' => ['status' => 'needs_attention']]));
        self::assertEqualsCanonicalizing([$errors->getUuid(), $warnings->getUuid()], self::listedUuids($crawler));
    }

    /**
     * @test
     */
    public function the_needs_attention_filter_lists_nothing_when_nothing_needs_attention(): void
    {
        $client = self::signedInClient();
        self::createPage('Clean', 'clean', 'Hello');

        $crawler = $client->request('GET', '/admin/cms/entries/page?' . http_build_query(['criteria' => ['status' => 'needs_attention']]));

        self::assertResponseIsSuccessful();
        self::assertSame([], self::listedUuids($crawler));
    }

    /**
     * @test
     */
    public function the_content_badge_counts_the_errors_and_warnings_that_need_attention_on_every_admin_page(): void
    {
        $client = self::signedInClient();
        self::createPage('Clean', 'clean', 'Hello');

        $crawler = $client->request('GET', '/admin/');
        self::assertResponseIsSuccessful();
        $content = $crawler->filter('.sylius-admin-menu a[href="/admin/cms"]');
        self::assertCount(1, $content);
        self::assertNull($content->attr('data-cms-problem-count'));
        // The stylesheet that shows the badge loads on every admin page
        self::assertCount(1, $crawler->filter('link[rel="stylesheet"][href="/bundles/setonosyliuscmsplugin/admin/menu.css"]'));

        self::pageWithProblems();
        self::createPageWithBlocks('Contact', 'contact', [new StoredBlock(self::PROMO, 'promo', 1)]);

        foreach (['/admin/', '/admin/products/', '/admin/cms'] as $page) {
            $crawler = $client->request('GET', $page);
            self::assertResponseIsSuccessful();
            $content = $crawler->filter('.sylius-admin-menu a[href="/admin/cms"]');
            self::assertSame('3', $content->attr('data-cms-problem-count'));
            self::assertSame('error', $content->attr('data-cms-problems'));
            self::assertSame('1 error, 2 warnings', $content->attr('title'));
            self::assertCount(1, $crawler->filter('link[rel="stylesheet"][href="/bundles/setonosyliuscmsplugin/admin/menu.css"]'));
        }
    }

    /**
     * A never-published page in en_US and da_DK, with a heading whose Danish text doesn't fit and a block of a type
     * the code no longer has
     */
    private static function pageWithProblems(string $title = 'About us', string $slug = 'about-us'): PageInterface
    {
        return self::createPageWithBlocks($title, $slug, [
            new StoredBlock(self::PROMO, 'promo', 1),
            new StoredBlock(self::HEADING, 'heading', 1, fields: ['text' => ['en_US' => 'Hello', 'da_DK' => 42]]),
        ]);
    }

    private static function signedInClient(): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');

        return $client;
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

    /**
     * The grid's row of the entry, whose title cell names it
     */
    private static function row(Crawler $crawler, EntryInterface $entry): Crawler
    {
        $row = $crawler->filterXPath(sprintf('//table/tbody/tr[.//*[@data-entry="%s"]]', $entry->getUuid()));
        self::assertCount(1, $row);

        return $row;
    }
}
