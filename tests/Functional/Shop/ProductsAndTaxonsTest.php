<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Doctrine\ORM\EntityManagerInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Catalog;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\ProfiledQueries;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The Products and Taxons blocks: hand-picked lists shown in the editor's order, of only the products and taxons the
 * shopper may see. Products show as the shop's own product cards, and taxons as tiles that lead to their pages. See
 * "Products" and "Taxons" in docs/spec/blocks.md, and "List fields" in docs/spec/sources.md
 */
final class ProductsAndTaxonsTest extends WebTestCase
{
    use Catalog;
    use Entries;
    use ProfiledQueries;

    private const PRODUCTS = '0193a5be-0000-7000-8000-0000000000c1';

    private const OTHER_PRODUCTS = '0193a5be-0000-7000-8000-0000000000c2';

    private const TAXONS = '0193a5be-0000-7000-8000-0000000000c3';

    /**
     * @test
     */
    public function it_shows_the_shop_s_product_cards_of_the_visible_products_in_the_editor_s_order(): void
    {
        $client = self::createClient();
        self::createProduct('MUG', 'Mug');
        self::createProduct('CAP', 'Cap', enabled: false);
        self::createProduct('T_SHIRT', 'T-shirt');
        self::createProduct('HOODIE', 'Hoodie', channels: []);
        self::createPageWithBlocks('Shop now', 'shop-now', [self::products(self::PRODUCTS, ['T_SHIRT', 'CAP', 'MUG', 'HOODIE', 'DELETED'])], published: true);

        $crawler = $client->request('GET', '/en_US/shop-now');

        self::assertResponseIsSuccessful();
        $grid = $crawler->filter('.cms-tree > .cms-products');
        self::assertSame('cms-products cms-products--grid', $grid->attr('class'));
        $items = $grid->children();
        self::assertSame(['cms-products__item', 'cms-products__item'], $items->each(static fn (Crawler $item): ?string => $item->attr('class')));
        // Each item is the shop's own product card, with the product's name, link and price
        self::assertSame(['T-shirt', 'Mug'], $items->filter('.sylius-product-name')->each(static fn (Crawler $name): string => $name->text()));
        self::assertSame('/en_US/products/t-shirt', $items->first()->filter('a.sylius-product-name')->attr('href'));
        self::assertCount(2, $items->filter('[data-test-product] .sylius-product-price'));
    }

    /**
     * @test
     */
    public function it_shows_nothing_when_shoppers_can_see_none_of_the_products(): void
    {
        $client = self::createClient();
        self::createProduct('CAP', 'Cap', enabled: false);
        self::createPageWithBlocks('Shop now', 'shop-now', [self::products(self::PRODUCTS, ['CAP'])], published: true);

        $crawler = $client->request('GET', '/en_US/shop-now');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('.cms-products'));
    }

    /**
     * @test
     */
    public function it_shows_tiles_of_the_visible_taxons_in_the_editor_s_order_that_lead_to_their_pages(): void
    {
        $client = self::createClient();
        $shirts = self::createTaxon('T_SHIRTS', 'T-shirts');
        self::addTaxonImage($shirts, 'ab/cd/t-shirts.jpg');
        self::addTaxonImage($shirts, 'ab/cd/t-shirts-2.jpg');
        self::createTaxon('CAPS', 'Caps', enabled: false);
        self::createTaxon('MUGS', 'Mugs');
        self::createPageWithBlocks('Shop now', 'shop-now', [self::taxons(self::TAXONS, ['MUGS', 'CAPS', 'T_SHIRTS', 'DELETED'])], published: true);

        $crawler = $client->request('GET', '/en_US/shop-now');

        self::assertResponseIsSuccessful();
        $links = $crawler->filter('.cms-tree > .cms-taxons > .cms-taxons__item');
        self::assertSame(
            [['Mugs', '/en_US/taxons/mugs', null], ['T-shirts', '/en_US/taxons/t-shirts', 'http://localhost/media/cache/resolve/sylius_shop_product_thumbnail/ab/cd/t-shirts.jpg']],
            $links->each(static fn (Crawler $link): array => [
                $link->filter('.cms-taxons__name')->text(),
                $link->attr('href'),
                0 === $link->filter('img')->count() ? null : $link->filter('img')->attr('src'),
            ]),
        );
    }

    /**
     * @test
     */
    public function it_loads_the_products_and_the_taxons_of_the_page_with_one_query_each(): void
    {
        $client = self::createClient();
        self::createProduct('MUG', 'Mug');
        self::createProduct('CAP', 'Cap');
        self::createProduct('T_SHIRT', 'T-shirt');
        self::createTaxon('T_SHIRTS', 'T-shirts');
        self::createTaxon('MUGS', 'Mugs');
        self::createPageWithBlocks('Shop now', 'shop-now', [
            self::products(self::PRODUCTS, ['MUG', 'T_SHIRT']),
            self::products(self::OTHER_PRODUCTS, ['CAP', 'MUG']),
            self::taxons(self::TAXONS, ['MUGS', 'T_SHIRTS']),
        ], published: true);
        // The queries of a request are profiled from a kernel that's booted afresh, which comes after the first request
        $client->request('GET', '/');

        $client->enableProfiler();
        $crawler = $client->request('GET', '/en_US/shop-now');

        self::assertResponseIsSuccessful();
        self::assertCount(4, $crawler->filter('.cms-products__item'));
        self::assertCount(2, $crawler->filter('.cms-taxons__item'));
        self::assertCount(1, self::queriesByCode($client, 'sylius_product'), 'One query loads the products');
        self::assertCount(1, self::queriesByCode($client, 'sylius_taxon'), 'One query loads the taxons');
    }

    /**
     * A tile reads its taxon's slug, name and images, which load with the taxons, so the tiles run no query of their own
     *
     * @test
     */
    public function it_runs_one_query_for_a_taxons_block_whose_tiles_show_each_taxon_s_name_and_image(): void
    {
        $client = self::createClient();
        foreach (['MUGS' => 'Mugs', 'T_SHIRTS' => 'T-shirts', 'CAPS' => 'Caps'] as $code => $name) {
            self::addTaxonImage(self::createTaxon($code, $name), sprintf('%s.jpg', strtolower($code)));
        }
        self::createPageWithBlocks('Empty', 'empty', [], published: true);
        self::createPageWithBlocks('Shop now', 'shop-now', [self::taxons(self::TAXONS, ['MUGS', 'T_SHIRTS', 'CAPS'])], published: true);
        // The queries of a request are profiled from a kernel that's booted afresh, which comes after the first request
        $client->request('GET', '/');

        $client->enableProfiler();
        $client->request('GET', '/en_US/empty');
        $withoutTheBlock = count(self::queries($client));

        $client->enableProfiler();
        $crawler = $client->request('GET', '/en_US/shop-now');

        self::assertSame(['Mugs', 'T-shirts', 'Caps'], $crawler->filter('.cms-taxons__name')->each(static fn (Crawler $name): string => $name->text()));
        self::assertCount(3, $crawler->filter('.cms-taxons__item > img.cms-taxons__image'));
        self::assertCount($withoutTheBlock + 1, self::queries($client), 'One query loads the taxons with what their tiles show');
    }

    /**
     * @test
     */
    public function it_flags_a_hand_picked_taxon_that_was_deleted_as_a_broken_reference_on_the_overview_page(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');
        $caps = self::createTaxon('CAPS', 'Caps');
        self::createTaxon('MUGS', 'Mugs');
        $page = self::createPageWithBlocks('Shop now', 'shop-now', [self::taxons(self::TAXONS, ['CAPS', 'MUGS'])], published: true);

        /** @var EntityManagerInterface $manager */
        $manager = self::getContainer()->get('doctrine.orm.entity_manager');
        $manager->remove($caps);
        $manager->flush();

        $problems = $client->request('GET', sprintf('/admin/cms/entry/%s', $page->getUuid()))->filter('[data-section="problems"] [data-problem]');
        self::assertSame(
            [['broken_reference', self::TAXONS, 'Taxons in the Taxons block refers to a taxon that was deleted, so it isn\'t shown.']],
            $problems->each(static fn (Crawler $problem): array => [$problem->attr('data-problem'), $problem->attr('data-block'), $problem->filter('.description')->text()]),
        );
        self::assertSame(['Mugs'], $client->request('GET', '/en_US/shop-now')->filter('.cms-taxons__name')->each(static fn (Crawler $name): string => $name->text()));
    }

    /**
     * @param list<string> $codes
     */
    private static function products(string $key, array $codes): StoredBlock
    {
        return new StoredBlock($key, 'products', 1, fields: ['products' => $codes]);
    }

    /**
     * @param list<string> $codes
     */
    private static function taxons(string $key, array $codes): StoredBlock
    {
        return new StoredBlock($key, 'taxons', 1, fields: ['taxons' => $codes]);
    }

    /**
     * The SQL of the queries the request ran that read from the table by code, leaving out the shop's own queries, such
     * as those of its taxon menu
     *
     * @return list<string>
     */
    private static function queriesByCode(KernelBrowser $client, string $table): array
    {
        return array_values(array_filter(self::queries($client), static fn (string $sql): bool => 1 === preg_match(sprintf('/FROM %s \w+ .*\bcode IN \(/', $table), $sql)));
    }
}
