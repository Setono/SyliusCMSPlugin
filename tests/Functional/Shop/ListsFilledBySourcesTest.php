<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\ListSourcesKernel;
use Setono\SyliusCMSPlugin\Tests\Support\Catalog;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpKernel\Profiler\Profile;

/**
 * Products and Taxons blocks filled by a source: the first $limit items the source lists, in its order, resolved only
 * when the template reads the list. A disabled source keeps resolving, and one the code no longer has yields nothing
 * and is flagged. See "List fields", "The contract" and "Paging and rendering" in docs/spec/sources.md
 */
final class ListsFilledBySourcesTest extends WebTestCase
{
    use Catalog;
    use Entries;

    private const PRODUCTS = '0193a5be-0000-7000-8000-0000000000d1';

    private const OTHER = '0193a5be-0000-7000-8000-0000000000d2';

    protected static function getKernelClass(): string
    {
        return ListSourcesKernel::class;
    }

    /**
     * @test
     */
    public function it_shows_the_first_limit_products_in_the_taxon_in_the_order_the_editor_picked(): void
    {
        $client = self::createClient();
        $shirts = self::createTaxon('T_SHIRTS', 'T-shirts');
        foreach (['STRIPED' => 'Striped', 'BASIC' => 'Basic', 'VNECK' => 'V-neck', 'ANCHOR' => 'Anchor', 'PLAIN' => 'Plain'] as $code => $name) {
            self::placeInTaxon(self::createProduct($code, $name), $shirts);
        }
        self::placeInTaxon(self::createProduct('HIDDEN', 'Aaa hidden', enabled: false), $shirts);
        self::createProduct('MUG', 'A mug');
        self::createPageWithBlocks('Shirts', 'shirts', [
            self::products(self::PRODUCTS, ['$source' => 'products_in_taxon', '$params' => ['taxon' => 'T_SHIRTS', 'includeChildren' => false, 'sort' => 'name'], '$limit' => 3]),
        ], published: true);

        $crawler = $client->request('GET', '/en_US/shirts');

        self::assertResponseIsSuccessful();
        self::assertSame(['Anchor', 'Basic', 'Plain'], self::names($crawler));
    }

    /**
     * @test
     */
    public function it_shows_the_taxons_below_a_taxon_as_tiles(): void
    {
        $client = self::createClient();
        $clothing = self::createTaxon('CLOTHING', 'Clothing');
        self::createChildTaxon($clothing, 'T_SHIRTS', 'T-shirts');
        self::createChildTaxon($clothing, 'CAPS', 'Caps');
        self::createChildTaxon($clothing, 'HIDDEN', 'Hidden', enabled: false);
        self::createPageWithBlocks('Clothing', 'clothing', [
            new StoredBlock(self::PRODUCTS, 'taxons', 1, fields: ['taxons' => ['$source' => 'taxon_children', '$params' => ['taxon' => 'CLOTHING'], '$limit' => 8]]),
        ], published: true);

        $crawler = $client->request('GET', '/en_US/clothing');

        self::assertResponseIsSuccessful();
        self::assertSame(['T-shirts', 'Caps'], $crawler->filter('.cms-taxons__name')->each(static fn (Crawler $name): string => $name->text()));
    }

    /**
     * @test
     */
    public function it_shows_what_a_project_s_source_lists(): void
    {
        $client = self::createClient();
        self::createProduct('FEATURED_B', 'Featured B');
        self::createProduct('FEATURED_A', 'Featured A');
        self::createProduct('OTHER', 'Other');
        self::createPageWithBlocks('Featured', 'featured', [self::products(self::PRODUCTS, ['$source' => 'featured', '$params' => new \stdClass(), '$limit' => 8])], published: true);

        self::assertSame(['Featured A', 'Featured B'], self::names($client->request('GET', '/en_US/featured')));
    }

    /**
     * @test
     */
    public function it_keeps_resolving_a_list_filled_by_a_disabled_source(): void
    {
        $client = self::createClient();
        self::createProduct('OLD', 'Old')->setCreatedAt(new \DateTime('2026-01-01'));
        self::createProduct('NEW', 'New')->setCreatedAt(new \DateTime('2026-11-01'));
        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('sylius.manager.product');
        $manager->flush();
        self::createPageWithBlocks('New in', 'new-in', [self::products(self::PRODUCTS, ['$source' => 'newest_products', '$params' => [], '$limit' => 8])], published: true);

        self::assertSame(['New', 'Old'], self::names($client->request('GET', '/en_US/new-in')));
    }

    /**
     * @test
     */
    public function it_shows_nothing_for_a_list_filled_by_a_source_the_code_no_longer_has_and_flags_it(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');
        self::createProduct('MUG', 'Mug');
        $page = self::createPageWithBlocks('Shop now', 'shop-now', [self::products(self::PRODUCTS, ['$source' => 'bestsellers', '$params' => [], '$limit' => 8])], published: true);

        self::assertCount(0, $client->request('GET', '/en_US/shop-now')->filter('.cms-products'));

        $problems = $client->request('GET', sprintf('/admin/cms/entry/%s', $page->getUuid()))->filter('[data-section="problems"] [data-problem]');
        self::assertSame(
            [['unknown_source', self::PRODUCTS, 'Products in the Products block is filled by the source "bestsellers", which the code no longer has, so it lists nothing.']],
            $problems->each(static fn (Crawler $problem): array => [$problem->attr('data-problem'), $problem->attr('data-block'), $problem->filter('.description')->text()]),
        );
    }

    /**
     * @test
     */
    public function it_runs_no_query_for_a_list_until_its_template_reads_it_and_then_takes_its_first_slice_without_counting(): void
    {
        $client = self::createClient();
        $shirts = self::createTaxon('T_SHIRTS', 'T-shirts');
        self::placeInTaxon(self::createProduct('BASIC', 'Basic'), $shirts);
        self::placeInTaxon(self::createProduct('PLAIN', 'Plain'), $shirts);
        $inTaxon = ['$source' => 'products_in_taxon', '$params' => ['taxon' => 'T_SHIRTS'], '$limit' => 8];
        self::createPageWithBlocks('Unread', 'unread', [new StoredBlock(self::PRODUCTS, 'unread_products', 1, fields: ['products' => $inTaxon])], published: true);
        self::createPageWithBlocks('Read', 'read', [self::products(self::OTHER, $inTaxon)], published: true);
        // The queries of a request are profiled from a kernel that's booted afresh, which comes after the first request
        $client->request('GET', '/');

        $client->enableProfiler();
        $client->request('GET', '/en_US/unread');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('.app-unread-products', 'Not read');
        self::assertSame([], self::productTaxonQueries($client));

        $client->enableProfiler();
        $crawler = $client->request('GET', '/en_US/read');
        self::assertSame(['Basic', 'Plain'], self::names($crawler));
        $queries = self::productTaxonQueries($client);
        self::assertCount(1, $queries, 'One query takes the first slice');
        self::assertStringNotContainsStringIgnoringCase('count(', $queries[0]);
        self::assertStringContainsString('LIMIT 8', $queries[0]);
    }

    /**
     * @param array<string, mixed> $value
     */
    private static function products(string $key, array $value): StoredBlock
    {
        return new StoredBlock($key, 'products', 1, fields: ['products' => $value]);
    }

    /**
     * The product names. Typed loosely, since the lowest DomCrawler types each() as a bare array
     *
     * @return array<mixed>
     */
    private static function names(Crawler $crawler): array
    {
        return $crawler->filter('.cms-tree > .cms-products .sylius-product-name')->each(static fn (Crawler $name): string => $name->text());
    }

    /**
     * The SQL of the queries the request ran against the products' places in taxons, which only the In taxon source reads
     *
     * @return list<string>
     */
    private static function productTaxonQueries(KernelBrowser $client): array
    {
        $profile = $client->getProfile();
        self::assertInstanceOf(Profile::class, $profile, 'The request was profiled');
        $collector = $profile->getCollector('db');
        self::assertInstanceOf(DoctrineDataCollector::class, $collector);

        $byConnection = $collector->getQueries();
        self::assertIsArray($byConnection);

        $queries = [];
        foreach ($byConnection as $connectionQueries) {
            self::assertIsArray($connectionQueries);
            foreach ($connectionQueries as $query) {
                self::assertIsArray($query);
                self::assertIsString($query['sql']);
                if (str_contains($query['sql'], 'sylius_product_taxon')) {
                    $queries[] = $query['sql'];
                }
            }
        }

        return $queries;
    }
}
