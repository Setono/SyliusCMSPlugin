<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Source\Product;

use Pagerfanta\Adapter\AdapterInterface;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Setono\SyliusCMSPlugin\Attribute\AsSource;
use Setono\SyliusCMSPlugin\Query\VisibleProductsQueryInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Source\SourceInterface;
use Sylius\Component\Core\Model\ProductInterface;

/**
 * "Same taxon": the other products the shopper may see in the main taxon of the host page's product, by their position
 * there, then by their ID. It leaves that product out, and yields nothing when it has no main taxon. It needs the
 * product, so it's offered only where a product is available, and yields nothing without one. See "Product sources"
 * in docs/spec/sources.md
 */
#[AsSource(code: self::CODE, label: 'setono_sylius_cms.source.same_taxon_products', of: ProductInterface::class, needs: ['product'])]
final readonly class SameTaxonProductsSource implements SourceInterface
{
    public const CODE = 'same_taxon_products';

    public function __construct(
        private VisibleProductsQueryInterface $visibleProducts,
        private TaxonFilter $taxonFilter,
    ) {
    }

    public function resolve(object $params, RenderContext $context): AdapterInterface
    {
        $product = $context->get('product');
        if (!$product instanceof ProductInterface) {
            return new ArrayAdapter([]);
        }

        $taxon = $product->getMainTaxon();
        if (null === $taxon) {
            return new ArrayAdapter([]);
        }

        $queryBuilder = $this->visibleProducts->createQueryBuilder($context)
            ->andWhere('product <> :cms_source_host_product')
            ->setParameter('cms_source_host_product', $product)
        ;
        $this->taxonFilter->keepInTaxon($queryBuilder, $taxon, false);
        $this->taxonFilter->orderByPosition($queryBuilder, $taxon, false);
        $queryBuilder->addOrderBy('product.id', 'ASC');

        // No collection is fetched with the products, so the paginator pages the query as it is
        return new QueryAdapter($queryBuilder, false);
    }
}
