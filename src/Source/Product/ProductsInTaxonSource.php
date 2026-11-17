<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Source\Product;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use Pagerfanta\Adapter\AdapterInterface;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Setono\SyliusCMSPlugin\Attribute\AsSource;
use Setono\SyliusCMSPlugin\Query\VisibleProductsQueryInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Source\SourceInterface;
use Sylius\Component\Core\Model\ProductInterface;

/**
 * "In taxon": the products the shopper may see in a taxon, or in it or any taxon below it, by their position there,
 * newest first, by the lowest price of their enabled variants in the channel or by their name in the locale, then by
 * their ID. See "Product sources" in docs/spec/sources.md
 */
#[AsSource(code: self::CODE, label: 'setono_sylius_cms.source.products_in_taxon', of: ProductInterface::class, params: ProductsInTaxonParams::class)]
final readonly class ProductsInTaxonSource implements SourceInterface
{
    public const CODE = 'products_in_taxon';

    /** What a product without a price in the channel sorts as: the most a price holds, so it comes last */
    private const UNPRICED = 2147483647;

    /**
     * @param class-string $channelPricingClass
     */
    public function __construct(
        private VisibleProductsQueryInterface $visibleProducts,
        private TaxonFilter $taxonFilter,
        private string $channelPricingClass,
    ) {
    }

    public function resolve(object $params, RenderContext $context): AdapterInterface
    {
        if (!$params instanceof ProductsInTaxonParams) {
            throw new \InvalidArgumentException(sprintf('The %s source takes %s, got %s', self::CODE, ProductsInTaxonParams::class, $params::class));
        }

        $taxon = $params->taxon;
        if (null === $taxon) {
            return new ArrayAdapter([]);
        }

        $queryBuilder = $this->visibleProducts->createQueryBuilder($context);
        $this->taxonFilter->keepInTaxon($queryBuilder, $taxon, $params->includeChildren);

        switch ($params->sort) {
            case ProductsInTaxonParams::SORT_NEWEST:
                // A product created later in the same second has a higher ID
                $queryBuilder->addOrderBy('product.createdAt', 'DESC')->addOrderBy('product.id', 'DESC');

                break;
            case ProductsInTaxonParams::SORT_PRICE:
                $this->orderByPrice($queryBuilder, $context);
                $queryBuilder->addOrderBy('product.id', 'ASC');

                break;
            case ProductsInTaxonParams::SORT_NAME:
                self::orderByName($queryBuilder, $context);
                $queryBuilder->addOrderBy('product.id', 'ASC');

                break;
            default:
                // By position, also for a sort this source no longer has
                $this->taxonFilter->orderByPosition($queryBuilder, $taxon, $params->includeChildren);
                $queryBuilder->addOrderBy('product.id', 'ASC');
        }

        // No collection is fetched with the products, so the paginator pages the query as it is
        return new QueryAdapter($queryBuilder, false);
    }

    /**
     * By the lowest price of the product's enabled variants in the channel, lowest first
     */
    private function orderByPrice(QueryBuilder $queryBuilder, RenderContext $context): void
    {
        $queryBuilder
            ->addSelect(sprintf(
                '(SELECT COALESCE(MIN(cms_source_pricing.price), %d) FROM %s cms_source_pricing INNER JOIN cms_source_pricing.productVariant cms_source_variant WHERE cms_source_variant.product = product AND cms_source_variant.enabled = :cms_source_variant_enabled AND cms_source_pricing.channelCode = :cms_source_channel_code) AS HIDDEN cms_source_price',
                self::UNPRICED,
                $this->channelPricingClass,
            ))
            ->setParameter('cms_source_variant_enabled', true, Types::BOOLEAN)
            ->setParameter('cms_source_channel_code', $context->channel->getCode())
            ->addOrderBy('cms_source_price', 'ASC')
        ;
    }

    /**
     * By the product's name in the locale, A to Z. The visible-products query keeps only products translated into it
     */
    private static function orderByName(QueryBuilder $queryBuilder, RenderContext $context): void
    {
        $queryBuilder
            ->innerJoin('product.translations', 'cms_source_translation', 'WITH', 'cms_source_translation.locale = :cms_source_locale')
            ->setParameter('cms_source_locale', $context->localeCode)
            ->addOrderBy('cms_source_translation.name', 'ASC')
        ;
    }
}
