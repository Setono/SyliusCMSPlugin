<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Catalog;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\Placements;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * On a product page, a snippet's Products blocks list the product's associated products, of one association type or
 * every type, or the other products in its main taxon. See "Product sources" in docs/spec/sources.md
 */
final class HostPageSourcesTest extends WebTestCase
{
    use Catalog;
    use Entries;
    use Placements;

    private const PRODUCTS = '0193a5be-0000-7000-8000-0000000000e1';

    /**
     * @test
     */
    public function it_lists_the_products_associated_with_the_product_of_the_product_page_of_every_type_or_one(): void
    {
        $client = self::createClient();
        $shirt = self::createProduct('T_SHIRT', 'T-shirt');
        $cap = self::createProduct('CAP', 'Cap');
        $belt = self::createProduct('BELT', 'Belt');
        $polo = self::createProduct('POLO', 'Polo');
        $mug = self::createProduct('MUG', 'Mug');
        self::associate($shirt, self::createAssociationType('SIMILAR', 'Similar products'), [$polo, $cap]);
        $accessories = self::createAssociationType('ACCESSORIES', 'Accessories');
        self::associate($shirt, $accessories, [$belt, $cap]);
        self::associate($mug, $accessories, [$polo]);
        self::place(self::listSnippet('Related', ['$source' => 'product_associations', '$params' => [], '$limit' => 8]), 'product.after_price');
        self::place(self::listSnippet('Accessories', ['$source' => 'product_associations', '$params' => ['associationType' => 'ACCESSORIES'], '$limit' => 8]), 'product.before_tabs');

        $crawler = $client->request('GET', '/en_US/products/t-shirt');

        self::assertResponseIsSuccessful();
        self::assertSame(['Cap', 'Belt', 'Polo'], self::names($crawler, 'product-after-price'));
        self::assertSame(['Cap', 'Belt'], self::names($crawler, 'product-before-tabs'));

        // Another product's associations
        $crawler = $client->request('GET', '/en_US/products/mug');

        self::assertSame(['Polo'], self::names($crawler, 'product-after-price'));
        self::assertSame(['Polo'], self::names($crawler, 'product-before-tabs'));
    }

    /**
     * An association type the store no longer has narrows the list to nothing, rather than listing every type
     *
     * @test
     */
    public function it_lists_nothing_for_an_association_type_the_store_no_longer_has(): void
    {
        $client = self::createClient();
        $shirt = self::createProduct('T_SHIRT', 'T-shirt');
        self::associate($shirt, self::createAssociationType('SIMILAR', 'Similar products'), [self::createProduct('POLO', 'Polo')]);
        self::place(self::listSnippet('Accessories', ['$source' => 'product_associations', '$params' => ['associationType' => 'ACCESSORIES'], '$limit' => 8]), 'product.after_price');

        $crawler = $client->request('GET', '/en_US/products/t-shirt');

        self::assertResponseIsSuccessful();
        self::assertSame([], self::names($crawler, 'product-after-price'));
    }

    /**
     * @test
     */
    public function it_lists_the_other_products_in_the_main_taxon_of_the_product_of_the_product_page(): void
    {
        $client = self::createClient();
        $shirts = self::createTaxon('T_SHIRTS', 'T-shirts');
        $basic = self::createProduct('BASIC', 'Basic');
        self::placeInTaxon(self::createProduct('STRIPED', 'Striped'), $shirts);
        self::placeInTaxon($basic, $shirts);
        self::placeInTaxon(self::createProduct('PLAIN', 'Plain'), $shirts);
        self::placeInTaxon(self::createProduct('VNECK', 'V-neck'), $shirts, 0);
        self::placeInTaxon(self::createProduct('MUG', 'Mug'), self::createTaxon('MUGS', 'Mugs'));
        self::setMainTaxon($basic, $shirts);
        self::place(self::listSnippet('More T-shirts', ['$source' => 'same_taxon_products', '$params' => [], '$limit' => 2]), 'product.after_price');

        $crawler = $client->request('GET', '/en_US/products/basic');

        self::assertResponseIsSuccessful();
        self::assertSame(['V-neck', 'Striped'], self::names($crawler, 'product-after-price'));
    }

    /**
     * @test
     */
    public function it_lists_nothing_in_the_same_taxon_when_the_product_has_no_main_taxon(): void
    {
        $client = self::createClient();
        $shirts = self::createTaxon('T_SHIRTS', 'T-shirts');
        self::placeInTaxon(self::createProduct('BASIC', 'Basic'), $shirts);
        self::placeInTaxon(self::createProduct('STRIPED', 'Striped'), $shirts);
        self::place(self::listSnippet('More T-shirts', ['$source' => 'same_taxon_products', '$params' => [], '$limit' => 8]), 'product.after_price');

        $crawler = $client->request('GET', '/en_US/products/basic');

        self::assertResponseIsSuccessful();
        self::assertSame([], self::names($crawler, 'product-after-price'));
    }

    /**
     * A published snippet for product pages with a Products block filled by the source
     *
     * @param array<string, mixed> $source
     */
    private static function listSnippet(string $title, array $source): SnippetInterface
    {
        return self::createSnippet($title, [new StoredBlock(self::PRODUCTS, 'products', 1, fields: ['products' => $source])], published: true, context: ['product']);
    }

    /**
     * The names of the products the slot's Products block lists
     *
     * @return array<mixed>
     */
    private static function names(Crawler $crawler, string $slot): array
    {
        return $crawler->filter(sprintf('.cms-slot--%s .cms-products .sylius-product-name', $slot))->each(static fn (Crawler $name): string => $name->text());
    }
}
