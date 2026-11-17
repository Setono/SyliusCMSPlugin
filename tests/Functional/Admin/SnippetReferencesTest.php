<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Problem\ProblemCount;
use Setono\SyliusCMSPlugin\Tests\Support\Blocks;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * What the admin shows about the Snippet blocks that show a snippet: the entries that use it, on its overview page, and
 * the references through which a snippet would render inside itself, as problems. See "Entry overview page" and
 * "Problems" in docs/spec/admin.md
 */
final class SnippetReferencesTest extends WebTestCase
{
    use Entries;

    private const HEADING = '0193a5be-0000-7000-8000-000000000001';

    private const REFERENCE = '0193a5be-0000-7000-8000-000000000002';

    private const A_SHOWS_B = '0193a5be-0000-7000-8000-0000000000ab';

    private const B_SHOWS_A = '0193a5be-0000-7000-8000-0000000000ba';

    /**
     * @test
     */
    public function it_lists_the_entries_whose_trees_show_the_snippet_under_used_in_with_where_each_shows_it(): void
    {
        $client = self::signedInClient();
        $footer = self::createSnippet('Footer', [self::heading(self::HEADING, 'Free shipping')], published: true);
        $about = self::createPageWithBlocks('About us', 'about-us', [Blocks::snippet(self::REFERENCE, $footer)], published: true);
        $contact = self::createPage('Contact', 'contact', 'Write to us', published: true);
        self::saveChanges($contact, [['op' => 'add_block', 'parent' => null, 'index' => 1, 'block' => ['key' => self::REFERENCE, 'type' => 'snippet', 'version' => 1, 'fields' => ['snippet' => $footer->getUuid()]]]]);
        $faq = self::createPageWithBlocks('FAQ', 'faq', [Blocks::snippet(self::REFERENCE, $footer)], published: true);
        self::saveChanges($faq, [['op' => 'remove_block', 'key' => self::REFERENCE]]);
        $promo = self::createSnippet('Promo', [Blocks::snippet(self::REFERENCE, $footer)]);
        self::createPage('Shipping', 'shipping', 'Free shipping', published: true);

        $crawler = $client->request('GET', self::overview($footer));

        self::assertResponseIsSuccessful();
        $section = $crawler->filter('[data-section="used_in"]');
        self::assertSame('Used in', $section->filter('h2')->text());
        self::assertSame([
            [$about->getUuid(), 'About us', 'Published'],
            [$contact->getUuid(), 'Contact', 'Draft'],
            [$faq->getUuid(), 'FAQ', 'Published, removed in the draft'],
            [$promo->getUuid(), 'Promo', 'Draft'],
        ], $section->filter('[data-used-in]')->each(static fn (Crawler $row): array => [
            $row->attr('data-used-in'),
            $row->filter('a')->text(),
            $row->filter('[data-use]')->text(),
        ]));
        self::assertSame(self::overview($about), $section->filter('[data-used-in] a')->first()->attr('href'));
    }

    /**
     * @test
     */
    public function it_says_when_no_entry_shows_the_snippet(): void
    {
        $client = self::signedInClient();
        $footer = self::createSnippet('Footer', [Blocks::snippet(self::REFERENCE, self::createSnippet('Other'))]);

        $crawler = $client->request('GET', self::overview($footer));

        self::assertSame('No entry shows it through a Snippet block yet.', $crawler->filter('[data-section="used_in"] p')->text());
    }

    /**
     * @test
     */
    public function it_shows_no_used_in_section_for_an_entry_that_is_not_a_snippet(): void
    {
        $client = self::signedInClient();
        $page = self::createPage('About us', 'about-us', 'Hello');

        $crawler = $client->request('GET', self::overview($page));

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('[data-section="used_in"]'));
    }

    /**
     * @test
     */
    public function it_flags_each_reference_through_which_a_snippet_would_render_inside_itself(): void
    {
        $client = self::signedInClient();
        $a = self::createSnippet('A', [self::heading(self::HEADING, 'A')], published: true);
        $b = self::createSnippet('B', [Blocks::snippet(self::B_SHOWS_A, $a)], published: true);
        self::assertEquals(new ProblemCount(), self::storedCount($b));

        // Publishing A showing B makes each show the other: B is counted again with A
        $a->setBlockTree(new StoredTree(null, [self::heading(self::HEADING, 'A'), Blocks::snippet(self::A_SHOWS_B, $b)]));
        self::entityManager()->flush();

        self::assertEquals(new ProblemCount(1, 0), self::storedCount($a));
        self::assertEquals(new ProblemCount(1, 0), self::storedCount($b));

        $crawler = $client->request('GET', self::overview($a));

        $problem = $crawler->filter('[data-section="problems"] [data-problem]');
        self::assertCount(1, $problem);
        self::assertSame(['snippet_inside_itself', 'error', self::A_SHOWS_B], [$problem->attr('data-problem'), $problem->attr('data-severity'), $problem->attr('data-block')]);
        self::assertSame('The Snippet block shows this snippet inside itself, directly or through other snippets, so it\'s skipped there.', $problem->filter('.description')->text());
        self::assertSame(sprintf('/admin/cms/entry/%s/builder?block=%s', $a->getUuid(), self::A_SHOWS_B), $problem->selectLink('Fix in builder')->attr('href'));
    }

    private static function overview(EntryInterface $entry): string
    {
        return sprintf('/admin/cms/entry/%s', $entry->getUuid());
    }

    private static function signedInClient(): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');

        return $client;
    }
}
