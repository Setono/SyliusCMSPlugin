<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Setono\SyliusCMSPlugin\Problem\ProblemCount;
use Setono\SyliusCMSPlugin\Tests\Support\Catalog;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Links in rich text: an <a> to where the link leads for the shopper, and plain text when it doesn't resolve, such as a
 * link to an entry that isn't published. See "Rich text" in docs/spec/content-model.md, "Rendering a block tree" in
 * docs/spec/rendering.md and "References to unavailable entries" in docs/spec/localization.md
 */
final class RichTextLinksTest extends WebTestCase
{
    use Catalog;
    use Entries;

    private const RICH_TEXT = '0193a5be-0000-7000-8000-0000000000d1';

    /**
     * @test
     */
    public function it_renders_a_link_to_a_published_entry_as_a_link_and_one_to_an_unpublished_entry_as_plain_text(): void
    {
        $client = self::createClient();
        $about = self::createPage('About us', 'about', published: true);
        $team = self::createPage('Our team', 'team');
        self::createPageWithBlocks('Start', 'start', [self::richText(
            ['type' => 'text', 'text' => 'Read '],
            ['type' => 'text', 'text' => 'about us', 'marks' => [self::link('entry', $about->getUuid(), 'history')]],
            ['type' => 'text', 'text' => ' or meet '],
            ['type' => 'text', 'text' => 'the team', 'marks' => [['type' => 'bold'], self::link('entry', $team->getUuid())]],
            ['type' => 'text', 'text' => '.'],
        )], published: true);

        $crawler = $client->request('GET', '/en_US/start');

        self::assertResponseIsSuccessful();
        self::assertSame(
            '<div class="cms-rich-text"><p>Read <a href="http://localhost/en_US/about#history">about us</a> or meet <strong>the team</strong>.</p></div>',
            $crawler->filter('.cms-tree > .cms-rich-text')->outerHtml(),
        );
    }

    /**
     * @test
     */
    public function it_renders_links_to_a_product_and_a_taxon_as_links_to_their_pages(): void
    {
        $client = self::createClient();
        self::createProduct('T_SHIRT_RED', 'Red T-shirt');
        self::createTaxon('MUGS', 'Mugs');
        self::createPageWithBlocks('Start', 'start', [self::richText(
            ['type' => 'text', 'text' => 'Red shirts', 'marks' => [self::link('product', 'T_SHIRT_RED')]],
            ['type' => 'text', 'text' => ' and '],
            ['type' => 'text', 'text' => 'mugs', 'marks' => [self::link('taxon', 'MUGS')]],
        )], published: true);

        $crawler = $client->request('GET', '/en_US/start');

        self::assertSame(
            [['Red shirts', 'http://localhost/en_US/products/red-t-shirt'], ['mugs', 'http://localhost/en_US/taxons/mugs']],
            $crawler->filter('.cms-tree > .cms-rich-text a')->each(static fn (Crawler $link): array => [$link->text(), $link->attr('href')]),
        );
    }

    /**
     * @test
     */
    public function it_marks_each_link_in_the_builder_s_preview_and_flags_one_that_does_not_resolve(): void
    {
        $client = self::createClient();
        $team = self::createPage('Our team', 'team');
        $page = self::createPageWithBlocks('Start', 'start', [self::richText(
            ['type' => 'text', 'text' => 'Meet '],
            ['type' => 'text', 'text' => 'the team', 'marks' => [self::link('entry', $team->getUuid())]],
        )], published: true);

        $preview = $client->request('GET', '/en_US/start?_cms_preview=' . self::previewToken($page));

        self::assertResponseIsSuccessful();
        self::assertSame(
            sprintf('<p>Meet <span data-cms-link-target="entry" data-cms-link-ref="%s">the team</span></p>', $team->getUuid()),
            $preview->filter('.cms-tree > .cms-rich-text p')->outerHtml(),
        );
        self::assertSame(
            'A link in Content shows as plain text here: what it links to isn\'t visible in this channel and locale, or was deleted.',
            $preview->filter('.cms-tree [data-cms-unresolved-link="content"]')->text(),
        );

        $shop = $client->request('GET', '/en_US/start');
        self::assertSame('<p>Meet the team</p>', $shop->filter('.cms-tree > .cms-rich-text p')->outerHtml());
        self::assertCount(0, $shop->filter('[data-cms-unresolved-link]'));
    }

    /**
     * @test
     */
    public function it_flags_a_link_to_a_product_that_was_deleted_as_a_broken_reference(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');
        $product = self::createProduct('T_SHIRT_RED', 'Red T-shirt');
        $page = self::createPageWithBlocks('Start', 'start', [self::richText(
            ['type' => 'text', 'text' => 'Red shirts', 'marks' => [self::link('product', 'T_SHIRT_RED')]],
        )], published: true);
        self::assertEquals(new ProblemCount(), self::storedCount($page));

        self::entityManager()->remove($product);
        self::entityManager()->flush();

        self::assertEquals(new ProblemCount(1, 0), self::storedCount($page), 'Deleting the product counts the entries that link to it again');
        $problems = $client->request('GET', sprintf('/admin/cms/entry/%s', $page->getUuid()))->filter('[data-section="problems"] [data-problem]');
        self::assertSame(
            [['broken_reference', self::RICH_TEXT, 'A link in Content in the Rich text block leads to a product that was deleted, so it shows as plain text.']],
            $problems->each(static fn (Crawler $problem): array => [$problem->attr('data-problem'), $problem->attr('data-block'), $problem->filter('.description')->text()]),
        );
    }

    /**
     * A Rich text block with one paragraph in en_US
     *
     * @param array<string, mixed> ...$content
     */
    private static function richText(array ...$content): StoredBlock
    {
        return new StoredBlock(self::RICH_TEXT, 'rich_text', 1, fields: ['content' => ['en_US' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => array_values($content)]]]]]);
    }

    /**
     * @return array{type: 'cmsLink', attrs: array{target: string, ref: string, anchor: string|null}}
     */
    private static function link(string $target, string $ref, ?string $anchor = null): array
    {
        return ['type' => 'cmsLink', 'attrs' => ['target' => $target, 'ref' => $ref, 'anchor' => $anchor]];
    }
}
