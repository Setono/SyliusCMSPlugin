<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Source;

use Doctrine\Persistence\ObjectManager;
use Pagerfanta\Adapter\AdapterInterface;
use Setono\SyliusCMSPlugin\Attribute\NoParams;
use Setono\SyliusCMSPlugin\Registry\SourceRegistryInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Source\Product\BestsellersParams;
use Setono\SyliusCMSPlugin\Source\Product\ProductsInTaxonParams;
use Setono\SyliusCMSPlugin\Source\Taxon\TaxonChildrenParams;
use Setono\SyliusCMSPlugin\Tests\Support\Bestsellers;
use Setono\SyliusCMSPlugin\Tests\Support\Catalog;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The shipped sources against a real database: what each lists, in its order, ending in the ID, paged in SQL.
 * See "Shipped sources" in docs/spec/sources.md
 */
final class ShippedSourcesTest extends KernelTestCase
{
    use Bestsellers;
    use Catalog;
    use Entries;

    /**
     * @test
     */
    public function it_lists_with_in_taxon_the_visible_products_in_the_taxon_by_their_position_in_it(): void
    {
        $shirts = self::createTaxon('T_SHIRTS', 'T-shirts');
        $mugs = self::createTaxon('MUGS', 'Mugs');
        self::placeInTaxon(self::createProduct('BASIC', 'Basic'), $shirts);
        self::placeInTaxon(self::createProduct('STRIPED', 'Striped'), $shirts);
        self::placeInTaxon(self::createProduct('HIDDEN', 'Hidden', enabled: false), $shirts);
        self::placeInTaxon(self::createProduct('PLAIN', 'Plain'), $shirts, 0);
        self::placeInTaxon(self::createProduct('MUG', 'Mug'), $mugs);

        $adapter = self::inTaxon($shirts);

        self::assertSame(['PLAIN', 'BASIC', 'STRIPED'], self::codes($adapter->getSlice(0, 10)));
        self::assertSame(3, $adapter->getNbResults());
    }

    /**
     * @test
     */
    public function it_lists_with_in_taxon_the_products_below_the_taxon_only_with_its_children_and_each_once(): void
    {
        $clothing = self::createTaxon('CLOTHING', 'Clothing');
        $shirts = self::createChildTaxon($clothing, 'T_SHIRTS', 'T-shirts');
        $caps = self::createChildTaxon($clothing, 'CAPS', 'Caps');
        $mugs = self::createTaxon('MUGS', 'Mugs');
        $jacket = self::createProduct('JACKET', 'Jacket');
        self::placeInTaxon($jacket, $clothing);
        $basic = self::createProduct('BASIC', 'Basic');
        self::placeInTaxon($basic, $shirts);
        self::placeInTaxon(self::createProduct('STRIPED', 'Striped'), $shirts);
        $cap = self::createProduct('CAP', 'Cap');
        self::placeInTaxon($cap, $caps);
        // In the taxon and below it, so at the position 1 in Clothing and at 0 in T-shirts
        self::placeInTaxon($basic, $clothing);
        self::placeInTaxon(self::createProduct('MUG', 'Mug'), $mugs);

        self::assertSame(['JACKET', 'BASIC'], self::codes(self::inTaxon($clothing)->getSlice(0, 10)));

        // By the lowest position in the taxon or below it: Jacket, Basic and Cap at 0, Striped at 1, then by ID
        $withChildren = self::inTaxon($clothing, includeChildren: true);
        self::assertSame(['JACKET', 'BASIC', 'CAP', 'STRIPED'], self::codes($withChildren->getSlice(0, 10)));
        self::assertSame(4, $withChildren->getNbResults());

        self::assertSame(['BASIC', 'STRIPED'], self::codes(self::inTaxon($shirts, includeChildren: true)->getSlice(0, 10)));
    }

    /**
     * @test
     */
    public function it_lists_with_in_taxon_the_newest_products_first_then_the_one_created_last(): void
    {
        $shirts = self::createTaxon('T_SHIRTS', 'T-shirts');
        foreach (['OLD' => '2026-01-01', 'NEW' => '2026-11-01', 'SAME_SECOND' => '2026-06-01', 'SAME_SECOND_LATER' => '2026-06-01'] as $code => $createdAt) {
            $product = self::createProduct($code, $code);
            $product->setCreatedAt(new \DateTime($createdAt));
            self::placeInTaxon($product, $shirts);
        }

        self::assertSame(['NEW', 'SAME_SECOND_LATER', 'SAME_SECOND', 'OLD'], self::codes(self::inTaxon($shirts, sort: ProductsInTaxonParams::SORT_NEWEST)->getSlice(0, 10)));
    }

    /**
     * @test
     */
    public function it_lists_with_in_taxon_the_products_by_the_lowest_price_of_their_enabled_variants_in_the_channel(): void
    {
        self::createChannel('OUTLET');
        $shirts = self::createTaxon('T_SHIRTS', 'T-shirts');
        $basic = self::createProduct('BASIC', 'Basic');
        self::priceProduct($basic, 1500);
        $striped = self::createProduct('STRIPED', 'Striped');
        self::priceProduct($striped, 2500);
        // A cheaper variant counts, but not a disabled one, nor a price in another channel
        self::addProductVariant($striped, 'STRIPED_SMALL', 1000);
        $plain = self::createProduct('PLAIN', 'Plain');
        self::priceProduct($plain, 2000);
        self::addProductVariant($plain, 'PLAIN_SMALL', 500, enabled: false);
        self::priceProduct($plain, 100, 'OUTLET');
        // Priced at the same as Plain, so after it by ID
        $same = self::createProduct('SAME', 'Same');
        self::priceProduct($same, 2000);
        // A product without a price in the channel comes last
        $unpriced = self::createProduct('UNPRICED', 'Unpriced', channels: ['WEB', 'OUTLET']);
        self::unpriceProduct($unpriced);
        foreach ([$unpriced, $basic, $striped, $plain, $same] as $product) {
            self::placeInTaxon($product, $shirts);
        }

        self::assertSame(['STRIPED', 'BASIC', 'PLAIN', 'SAME', 'UNPRICED'], self::codes(self::inTaxon($shirts, sort: ProductsInTaxonParams::SORT_PRICE)->getSlice(0, 10)));
    }

    /**
     * @test
     */
    public function it_lists_with_in_taxon_the_products_by_their_name_in_the_locale(): void
    {
        $shirts = self::createTaxon('T_SHIRTS', 'T-shirts');
        foreach (['B' => 'Basic', 'Z' => 'Anchor', 'A' => 'Striped', 'P' => 'Plain'] as $code => $name) {
            self::placeInTaxon(self::createProduct($code, $name), $shirts);
        }

        self::assertSame(['Z', 'B', 'P', 'A'], self::codes(self::inTaxon($shirts, sort: ProductsInTaxonParams::SORT_NAME)->getSlice(0, 10)));
    }

    /**
     * @test
     */
    public function it_pages_in_taxon_without_overlapping_or_skipping_products(): void
    {
        $shirts = self::createTaxon('T_SHIRTS', 'T-shirts');
        foreach (['A', 'B', 'C', 'D', 'E'] as $code) {
            // The same name, price and time, so only the ID orders them
            $product = self::createProduct($code, $code);
            $product->getTranslation('en_US')->setName('Same name');
            $product->setCreatedAt(new \DateTime('2026-06-01'));
            self::priceProduct($product, 1000);
            self::placeInTaxon($product, $shirts);
        }

        foreach ([ProductsInTaxonParams::SORT_POSITION, ProductsInTaxonParams::SORT_NEWEST, ProductsInTaxonParams::SORT_PRICE, ProductsInTaxonParams::SORT_NAME] as $sort) {
            $adapter = self::inTaxon($shirts, sort: $sort);
            $all = self::codes($adapter->getSlice(0, 5));
            $pages = [...self::codes($adapter->getSlice(0, 2)), ...self::codes($adapter->getSlice(2, 2)), ...self::codes($adapter->getSlice(4, 2))];

            self::assertSame($all, $pages, sprintf('Sorted by %s', $sort));
            self::assertCount(5, array_unique($all));
        }
    }

    /**
     * @test
     */
    public function it_lists_nothing_with_in_taxon_without_a_taxon(): void
    {
        self::placeInTaxon(self::createProduct('BASIC', 'Basic'), self::createTaxon('T_SHIRTS', 'T-shirts'));

        $adapter = self::source('products_in_taxon')->resolve(new ProductsInTaxonParams(), self::context());

        self::assertSame(0, $adapter->getNbResults());
    }

    /**
     * @test
     */
    public function it_lists_with_newest_the_visible_products_newest_first(): void
    {
        foreach (['OLD' => '2026-01-01', 'NEW' => '2026-11-01', 'MIDDLE' => '2026-06-01'] as $code => $createdAt) {
            self::createProduct($code, $code)->setCreatedAt(new \DateTime($createdAt));
        }
        self::createProduct('HIDDEN', 'Hidden', enabled: false)->setCreatedAt(new \DateTime('2026-11-02'));
        self::flushProducts();

        $adapter = self::source('newest_products')->resolve(new NoParams(), self::context());

        self::assertSame(['NEW', 'MIDDLE', 'OLD'], self::codes($adapter->getSlice(0, 10)));
        self::assertSame(['NEW', 'MIDDLE'], self::codes($adapter->getSlice(0, 2)));
        self::assertSame(3, $adapter->getNbResults());
    }

    /**
     * @test
     */
    public function it_lists_with_bestsellers_the_visible_products_scored_in_the_channel_highest_first_then_by_id(): void
    {
        self::createChannel('OUTLET');
        foreach (['BASIC', 'STRIPED', 'PLAIN', 'SAME', 'UNSCORED'] as $code) {
            self::createProduct($code, $code);
        }
        self::createProduct('HIDDEN', 'Hidden', enabled: false);
        self::createProduct('OUTLET_ONLY', 'Outlet only', channels: ['OUTLET']);
        // Same scores as Plain, created after it, so after it by ID. A score of a product that's gone joins nothing
        self::scoreProducts(['BASIC' => 2, 'STRIPED' => 7.5, 'SAME' => 4, 'PLAIN' => 4, 'HIDDEN' => 100, 'OUTLET_ONLY' => 50, 'GONE' => 20]);
        // Scores in another channel don't count
        self::scoreProducts(['UNSCORED' => 90, 'BASIC' => 80], 'OUTLET');

        $adapter = self::bestsellers();

        self::assertSame(['STRIPED', 'PLAIN', 'SAME', 'BASIC'], self::codes($adapter->getSlice(0, 10)));
        self::assertSame(['PLAIN', 'SAME'], self::codes($adapter->getSlice(1, 2)));
        self::assertSame(4, $adapter->getNbResults());
    }

    /**
     * @test
     */
    public function it_lists_with_bestsellers_the_scored_products_in_the_taxon_or_below_it_with_its_children(): void
    {
        $clothing = self::createTaxon('CLOTHING', 'Clothing');
        $shirts = self::createChildTaxon($clothing, 'T_SHIRTS', 'T-shirts');
        $mugs = self::createTaxon('MUGS', 'Mugs');
        $jacket = self::createProduct('JACKET', 'Jacket');
        self::placeInTaxon($jacket, $clothing);
        $basic = self::createProduct('BASIC', 'Basic');
        self::placeInTaxon($basic, $shirts);
        // In the taxon and below it, so listed once
        self::placeInTaxon($basic, $clothing);
        self::placeInTaxon(self::createProduct('STRIPED', 'Striped'), $shirts);
        self::placeInTaxon(self::createProduct('MUG', 'Mug'), $mugs);
        self::scoreProducts(['MUG' => 9, 'STRIPED' => 5, 'BASIC' => 3, 'JACKET' => 1]);

        self::assertSame(['BASIC', 'JACKET'], self::codes(self::bestsellers($clothing)->getSlice(0, 10)));
        $withChildren = self::bestsellers($clothing, includeChildren: true);
        self::assertSame(['STRIPED', 'BASIC', 'JACKET'], self::codes($withChildren->getSlice(0, 10)));
        self::assertSame(3, $withChildren->getNbResults());
    }

    /**
     * @test
     */
    public function it_never_comes_up_short_with_bestsellers_while_enough_visible_products_are_scored(): void
    {
        $shirts = self::createTaxon('T_SHIRTS', 'T-shirts');
        $scores = [];
        foreach (range(1, 12) as $rank) {
            // The best sellers are hidden, unscored or outside the taxon, and the rest of the list fills the first 8
            $code = sprintf('P%02d', $rank);
            $product = self::createProduct($code, $code, enabled: $rank > 2);
            if (3 !== $rank) {
                self::placeInTaxon($product, $shirts);
            }
            if (4 !== $rank) {
                $scores[$code] = 100 - $rank;
            }
        }
        self::scoreProducts($scores);

        self::assertSame(['P05', 'P06', 'P07', 'P08', 'P09', 'P10', 'P11', 'P12'], self::codes(self::bestsellers($shirts)->getSlice(0, 8)));
    }

    /**
     * @test
     */
    public function it_lists_nothing_with_bestsellers_until_the_scores_are_first_computed(): void
    {
        self::createProduct('BASIC', 'Basic');

        $adapter = self::bestsellers();

        self::assertSame([], self::codes($adapter->getSlice(0, 8)));
        self::assertSame(0, $adapter->getNbResults());
    }

    /**
     * @test
     */
    public function it_lists_with_children_of_a_taxon_the_enabled_taxons_right_below_it_by_their_position(): void
    {
        $clothing = self::createTaxon('CLOTHING', 'Clothing');
        self::createChildTaxon($clothing, 'T_SHIRTS', 'T-shirts');
        $caps = self::createChildTaxon($clothing, 'CAPS', 'Caps');
        self::createChildTaxon($clothing, 'HIDDEN', 'Hidden', enabled: false);
        self::createChildTaxon($clothing, 'JACKETS', 'Jackets');
        self::createChildTaxon($caps, 'BEANIES', 'Beanies');
        $caps->setPosition(0);
        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('sylius.manager.taxon');
        $manager->flush();

        $params = new TaxonChildrenParams();
        $params->taxon = $clothing;
        $adapter = self::source('taxon_children')->resolve($params, self::context());

        self::assertSame(['CAPS', 'T_SHIRTS', 'JACKETS'], self::codes($adapter->getSlice(0, 10)));
        self::assertSame(3, $adapter->getNbResults());
    }

    /**
     * @return AdapterInterface<mixed>
     */
    private static function inTaxon(TaxonInterface $taxon, bool $includeChildren = false, string $sort = ProductsInTaxonParams::SORT_POSITION): AdapterInterface
    {
        $params = new ProductsInTaxonParams();
        $params->taxon = $taxon;
        $params->includeChildren = $includeChildren;
        $params->sort = $sort;

        return self::source('products_in_taxon')->resolve($params, self::context());
    }

    /**
     * @return AdapterInterface<mixed>
     */
    private static function bestsellers(?TaxonInterface $taxon = null, bool $includeChildren = false): AdapterInterface
    {
        $params = new BestsellersParams();
        $params->taxon = $taxon;
        $params->includeChildren = $includeChildren;

        return self::source('bestsellers')->resolve($params, self::context());
    }

    private static function source(string $code): \Setono\SyliusCMSPlugin\Source\SourceInterface
    {
        $registry = self::getContainer()->get(SourceRegistryInterface::class);
        self::assertInstanceOf(SourceRegistryInterface::class, $registry);

        return $registry->get($code)->source;
    }

    /**
     * @param iterable<mixed> $items
     *
     * @return list<string>
     */
    private static function codes(iterable $items): array
    {
        $codes = [];
        foreach ($items as $item) {
            self::assertTrue($item instanceof ProductInterface || $item instanceof TaxonInterface);
            $codes[] = (string) $item->getCode();
        }

        return $codes;
    }

    private static function flushProducts(): void
    {
        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('sylius.manager.product');
        $manager->flush();
    }

    private static function context(): RenderContext
    {
        /** @var ChannelRepositoryInterface<ChannelInterface> $channels */
        $channels = self::getContainer()->get('sylius.repository.channel');
        $channel = $channels->findOneByCode('WEB');
        self::assertInstanceOf(ChannelInterface::class, $channel);

        return new RenderContext($channel, 'en_US', 'USD', null, '@guest', new \DateTimeImmutable());
    }
}
