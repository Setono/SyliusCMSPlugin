<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Setono\SyliusCMSPlugin\Tests\Support\Catalog;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\Placements;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Slots on product and taxon pages provide the product or the taxon to the snippets shown in them, from what their
 * template event passes or through a context resolver. See "Context keys", "Context resolvers" and "Stock slots" in
 * docs/spec/slots.md and "Rendering a slot" in docs/spec/rendering.md
 */
final class HostPageContextTest extends WebTestCase
{
    use Catalog;
    use Entries;
    use Placements;

    private const NOTE = '0193a5be-0000-7000-8000-000000000001';

    private const HEADING = '0193a5be-0000-7000-8000-000000000002';

    /**
     * @test
     */
    public function it_gives_a_snippet_that_needs_the_product_the_product_of_the_product_page_in_its_context(): void
    {
        $client = self::createClient();
        self::createProduct('T_SHIRT', 'T-shirt');
        self::place(self::createSnippet('Size guide', [self::note('Size guide for')], published: true, context: ['product']), 'product.after_price');

        $crawler = $client->request('GET', '/en_US/products/t-shirt');

        self::assertResponseIsSuccessful();
        self::assertSame(['Size guide for T-shirt'], $crawler->filter('.cms-slot--product-after-price .app-product-note')->each(static fn (Crawler $note): string => $note->text()));
    }

    /**
     * product.top sits in a layout event that fires on every page, and the product resolver finds a product only on a
     * product page
     *
     * @test
     */
    public function it_shows_product_top_only_on_product_pages(): void
    {
        $client = self::createClient();
        self::createProduct('T_SHIRT', 'T-shirt');
        $taxon = self::createTaxon('T_SHIRTS', 'T-shirts');
        self::place(self::createSnippet('Free returns', [self::note('Free returns on')], published: true, context: ['product']), 'product.top');

        $crawler = $client->request('GET', '/en_US/products/t-shirt');
        self::assertResponseIsSuccessful();
        self::assertSame(['Free returns on T-shirt'], $crawler->filter('.cms-slot--product-top .app-product-note')->each(static fn (Crawler $note): string => $note->text()));

        foreach (['/en_US/', '/en_US/taxons/' . $taxon->getSlug(), '/en_US/cart/', '/en_US/products/gone'] as $url) {
            $crawler = $client->request('GET', $url);

            self::assertCount(0, $crawler->filter('.cms-slot--product-top'), $url);
        }
    }

    /**
     * Sylius renders the taxon header in a sub-request of its own. The taxon's grid events don't pass the taxon, so
     * taxon.before_grid resolves it from the main request
     *
     * @test
     */
    public function it_shows_snippets_that_need_the_taxon_in_the_taxon_page_s_header_inside_its_sub_request_and_above_its_grid(): void
    {
        $client = self::createClient();
        $taxon = self::createTaxon('T_SHIRTS', 'T-shirts');
        self::placeInTaxon(self::createProduct('T_SHIRT', 'T-shirt'), $taxon);
        $snippet = self::createSnippet('Taxon banner', [self::heading(self::HEADING, 'Our t-shirts')], published: true, context: ['taxon']);
        self::place($snippet, 'taxon.header');
        self::place($snippet, 'taxon.before_grid');

        $crawler = $client->request('GET', '/en_US/taxons/' . $taxon->getSlug());

        self::assertResponseIsSuccessful();
        self::assertSame(['cms-slot cms-slot--taxon-header', 'cms-slot cms-slot--taxon-before-grid'], $crawler->filter('.cms-slot')->each(static fn (Crawler $slot): string => (string) $slot->attr('class')));

        $crawler = $client->request('GET', '/en_US/products/t-shirt');

        self::assertCount(0, $crawler->filter('.cms-slot'), 'A product page provides no taxon');
    }

    private static function note(string $text): StoredBlock
    {
        $block = new StoredBlock(self::NOTE, 'app_product_note', 1);
        $block->setValue('text', 'en_US', $text);

        return $block;
    }
}
