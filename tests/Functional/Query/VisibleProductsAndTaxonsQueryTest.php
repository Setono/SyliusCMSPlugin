<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Query;

use Setono\SyliusCMSPlugin\Query\VisibleProductsQueryInterface;
use Setono\SyliusCMSPlugin\Query\VisibleTaxonsQueryInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Tests\Support\Catalog;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The products and taxons queries that link targets and sources build on, mirroring Sylius's own shop listing.
 * See "Visible queries" in docs/spec/sources.md
 */
final class VisibleProductsAndTaxonsQueryTest extends KernelTestCase
{
    use Catalog;
    use Entries;

    /**
     * @test
     */
    public function it_keeps_an_enabled_product_in_the_channel_translated_into_the_locale(): void
    {
        self::createProduct('T_SHIRT', 'T-shirt');

        self::assertSame(['T_SHIRT'], self::visibleProducts(self::context()));
    }

    /**
     * @test
     */
    public function it_leaves_out_a_disabled_product(): void
    {
        self::createProduct('T_SHIRT', 'T-shirt');
        self::createProduct('MUG', 'Mug', enabled: false);

        self::assertSame(['T_SHIRT'], self::visibleProducts(self::context()));
    }

    /**
     * @test
     */
    public function it_leaves_out_a_product_that_is_not_in_the_channel(): void
    {
        self::createChannel('OUTLET');
        self::createProduct('T_SHIRT', 'T-shirt');
        self::createProduct('MUG', 'Mug', channels: ['OUTLET']);

        self::assertSame(['T_SHIRT'], self::visibleProducts(self::context()));
    }

    /**
     * @test
     */
    public function it_leaves_out_a_product_that_is_not_translated_into_the_locale(): void
    {
        // Sylius's example factory translates a product into every locale the store has, so the locale comes after
        self::createProduct('T_SHIRT', 'T-shirt');
        $mug = self::createProduct('MUG', 'Mug');
        self::addLocales('WEB', 'de_DE');
        self::translateProduct($mug, 'de_DE', 'Becher', 'becher');

        self::assertSame(['MUG'], self::visibleProducts(self::context('de_DE')));
        self::assertSame(['MUG', 'T_SHIRT'], self::visibleProducts(self::context()));
    }

    /**
     * @test
     */
    public function its_products_have_the_root_alias_product_and_no_order_by(): void
    {
        $queryBuilder = self::productsQuery()->createQueryBuilder(self::context());

        self::assertSame(['product'], $queryBuilder->getRootAliases());
        self::assertSame([], $queryBuilder->getDQLPart('orderBy'));
    }

    /**
     * @test
     */
    public function it_keeps_each_product_once_whatever_a_caller_joins(): void
    {
        self::createProduct('T_SHIRT', 'T-shirt');

        $queryBuilder = self::productsQuery()->createQueryBuilder(self::context())->andWhere('product.code = :code')->setParameter('code', 'T_SHIRT');

        self::assertCount(1, $queryBuilder->getQuery()->getResult());
    }

    /**
     * @test
     */
    public function it_keeps_an_enabled_taxon_and_leaves_out_a_disabled_one(): void
    {
        self::createTaxon('T_SHIRTS', 'T-shirts');
        self::createTaxon('MUGS', 'Mugs', enabled: false);

        /** @var list<TaxonInterface> $taxons */
        $taxons = self::taxonsQuery()->createQueryBuilder(self::context())->getQuery()->getResult();

        self::assertSame(['T_SHIRTS'], array_map(static fn (TaxonInterface $taxon): ?string => $taxon->getCode(), $taxons));
    }

    /**
     * @test
     */
    public function its_taxons_have_the_root_alias_taxon_and_no_order_by(): void
    {
        $queryBuilder = self::taxonsQuery()->createQueryBuilder(self::context());

        self::assertSame(['taxon'], $queryBuilder->getRootAliases());
        self::assertSame([], $queryBuilder->getDQLPart('orderBy'));
    }

    /**
     * @return list<string> the codes, sorted
     */
    private static function visibleProducts(RenderContext $context): array
    {
        /** @var list<ProductInterface> $products */
        $products = self::productsQuery()->createQueryBuilder($context)->getQuery()->getResult();

        $codes = array_map(static fn (ProductInterface $product): string => (string) $product->getCode(), $products);
        sort($codes);

        return $codes;
    }

    private static function context(string $localeCode = 'en_US'): RenderContext
    {
        /** @var ChannelRepositoryInterface<ChannelInterface> $channels */
        $channels = self::getContainer()->get('sylius.repository.channel');
        $channel = $channels->findOneByCode('WEB');
        self::assertInstanceOf(ChannelInterface::class, $channel);

        return new RenderContext($channel, $localeCode, 'USD', null, '@guest', new \DateTimeImmutable());
    }

    private static function productsQuery(): VisibleProductsQueryInterface
    {
        $query = self::getContainer()->get(VisibleProductsQueryInterface::class);
        self::assertInstanceOf(VisibleProductsQueryInterface::class, $query);

        return $query;
    }

    private static function taxonsQuery(): VisibleTaxonsQueryInterface
    {
        $query = self::getContainer()->get(VisibleTaxonsQueryInterface::class);
        self::assertInstanceOf(VisibleTaxonsQueryInterface::class, $query);

        return $query;
    }
}
