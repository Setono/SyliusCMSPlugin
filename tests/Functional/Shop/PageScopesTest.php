<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Catalog;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\Placements;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * A placement's page scope limits it to some shop pages, such as product pages in the Shoes taxon. See "Page scopes"
 * and "Mismatches" in docs/spec/slots.md and "Rendering a slot" in docs/spec/rendering.md
 */
final class PageScopesTest extends WebTestCase
{
    use Catalog;
    use Entries;
    use Placements;

    private const HEADING = '0193a5be-0000-7000-8000-000000000001';

    /**
     * @test
     */
    public function it_shows_a_placement_scoped_to_products_in_shoes_on_a_shoe_and_not_on_a_t_shirt(): void
    {
        $client = self::createClient();
        self::createCatalog();
        self::scope(self::place(self::snippet('Free shoe returns'), 'product.after_price'), [self::productInTaxon(['SHOES'])]);

        self::assertSame(['Free shoe returns'], self::headingsOn($client, '/en_US/products/sneakers'));
        self::assertSame([], self::headingsOn($client, '/en_US/products/t-shirt'));
    }

    /**
     * Through a taxon below Shoes, and through a taxon that isn't the product's main one
     *
     * @test
     */
    public function it_shows_a_placement_scoped_to_products_in_shoes_including_its_children_on_products_in_any_taxon_below_it(): void
    {
        $client = self::createClient();
        ['shoes' => $shoes] = self::createCatalog();
        $boots = self::createProduct('BOOTS', 'Boots');
        $boots->setMainTaxon(self::createTaxon('NEW', 'New'));
        self::placeInTaxon($boots, self::createChildTaxon($shoes, 'WINTER_SHOES', 'Winter shoes'));
        $placement = self::place(self::snippet('Shoe care'), 'product.after_price');

        self::scope($placement, [self::productInTaxon(['SHOES'])]);
        self::assertSame([], self::headingsOn($client, '/en_US/products/boots'));

        self::scope($placement, [self::productInTaxon(['SHOES'], includeChildren: true)]);
        self::assertSame(['Shoe care'], self::headingsOn($client, '/en_US/products/boots'));
        self::assertSame(['Shoe care'], self::headingsOn($client, '/en_US/products/sneakers'));
        self::assertSame([], self::headingsOn($client, '/en_US/products/t-shirt'));
    }

    /**
     * @test
     */
    public function it_shows_a_placement_whose_scope_is_negated_on_every_page_but_those_it_names(): void
    {
        $client = self::createClient();
        self::createCatalog();
        self::scope(self::place(self::snippet('Size guide'), 'product.after_price'), [self::productInTaxon(['SHOES'], negate: true)]);

        self::assertSame([], self::headingsOn($client, '/en_US/products/sneakers'));
        self::assertSame(['Size guide'], self::headingsOn($client, '/en_US/products/t-shirt'));
    }

    /**
     * @test
     */
    public function it_shows_a_placement_with_an_empty_scope_on_every_page_the_slot_appears_on(): void
    {
        $client = self::createClient();
        self::createCatalog();
        self::place(self::snippet('Free shipping'), 'product.after_price');

        self::assertSame(['Free shipping'], self::headingsOn($client, '/en_US/products/sneakers'));
        self::assertSame(['Free shipping'], self::headingsOn($client, '/en_US/products/t-shirt'));
    }

    /**
     * A product scope, AND a taxon page's scope
     *
     * @test
     */
    public function it_scopes_placements_to_single_products_and_to_taxon_pages(): void
    {
        $client = self::createClient();
        ['shoes' => $shoes] = self::createCatalog();
        self::scope(self::place(self::snippet('Sneakers story'), 'product.after_price'), [['kind' => 'product', 'negate' => false, 'params' => ['products' => ['SNEAKERS']]]]);
        self::scope(self::place(self::snippet('Shoe sale', context: ['taxon']), 'taxon.header'), [['kind' => 'taxon', 'negate' => false, 'params' => ['taxons' => ['SHOES']]]]);

        self::assertSame(['Sneakers story'], self::headingsOn($client, '/en_US/products/sneakers'));
        self::assertSame([], self::headingsOn($client, '/en_US/products/t-shirt'));
        self::assertSame(['Shoe sale'], self::headingsOn($client, '/en_US/taxons/' . $shoes->getSlug()));
        self::assertSame([], self::headingsOn($client, '/en_US/taxons/t-shirts'));
    }

    /**
     * A page scope reads the context its slot provides, not what the snippet declares, so a snippet shown on any page
     * can be in the footer everywhere and beside the images of shoes
     *
     * @test
     */
    public function it_publishes_and_shows_a_snippet_for_any_page_placed_in_the_footer_and_scoped_to_shoes_in_a_product_slot(): void
    {
        $client = self::createClient();
        self::createCatalog();
        $snippet = self::createSnippet('Free returns', [self::heading(self::HEADING, 'Free returns')]);
        self::place($snippet, 'shop.footer');
        self::scope(self::place($snippet, 'product.sidebar'), [self::productInTaxon(['SHOES'])]);

        self::publishEntry($snippet);

        $crawler = $client->request('GET', '/en_US/products/sneakers');
        self::assertSame(['cms-slot cms-slot--product-sidebar', 'cms-slot cms-slot--shop-footer'], $crawler->filter('.cms-slot')->each(static fn (Crawler $slot): string => (string) $slot->attr('class')));
        self::assertSame(['Free returns'], self::headingsOn($client, '/en_US/products/t-shirt'));
    }

    /**
     * Such as a project's condition kind a deploy removed: the condition is kept, and the placement shows nothing
     *
     * @test
     */
    public function it_keeps_but_shows_nothing_of_a_placement_whose_scope_has_a_condition_of_a_kind_the_code_no_longer_has_and_flags_it(): void
    {
        $client = self::createClient();
        self::createCatalog();
        $snippet = self::snippet('Brand story');
        $placement = self::place($snippet, 'product.after_price');
        $scope = [['kind' => 'brand', 'negate' => true, 'params' => ['brands' => ['ACME']]]];
        self::scopeUnchecked($placement, $scope);

        self::assertSame([], self::headingsOn($client, '/en_US/products/sneakers'));
        self::assertSame($scope, self::placements()->findOneByUuid($placement->getUuid())?->getPageScope());

        $client->loginUser(self::admin(), 'admin');
        $crawler = $client->request('GET', sprintf('/admin/cms/entry/%s', $snippet->getUuid()));

        $problem = $crawler->filter('[data-section="problems"] [data-problem="page_scope_mismatch"]');
        self::assertSame('warning', $problem->attr('data-severity'));
        self::assertSame('The page scope of the placement in "Product page: under the price" has a condition of the kind "brand", which the code no longer has, so the placement shows nothing.', $problem->filter('.description')->text());
    }

    /**
     * @return array{shoes: \Sylius\Component\Core\Model\TaxonInterface}
     */
    private static function createCatalog(): array
    {
        $shoes = self::createTaxon('SHOES', 'Shoes');
        self::placeInTaxon(self::createProduct('SNEAKERS', 'Sneakers'), $shoes);
        self::placeInTaxon(self::createProduct('T_SHIRT', 'T-shirt'), self::createTaxon('T_SHIRTS', 'T-shirts'));

        return ['shoes' => $shoes];
    }

    /**
     * @param list<string> $context
     */
    private static function snippet(string $heading, array $context = []): SnippetInterface
    {
        return self::createSnippet($heading, [self::heading(self::HEADING, $heading)], published: true, context: $context);
    }

    /**
     * The headings in the page's slots
     *
     * @return list<string>
     */
    private static function headingsOn(KernelBrowser $client, string $url): array
    {
        $crawler = $client->request('GET', $url);
        self::assertResponseIsSuccessful($url);

        return $crawler->filter('.cms-slot .cms-heading')->each(static fn (Crawler $heading): string => $heading->text());
    }
}
