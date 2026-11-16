<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin;

use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
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
        self::assertSame(['publishing', 'problems', 'urls'], $crawler->filter('[data-section]')->each(static fn (Crawler $section): ?string => $section->attr('data-section')));
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
}
