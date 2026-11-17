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
 * "Associations": the products the shopper may see that the host page's product is associated with, of one
 * association type or of every type, by their ID. It needs the product, so it's offered only where a product is
 * available, and yields nothing without one. See "Product sources" in docs/spec/sources.md
 */
#[AsSource(code: self::CODE, label: 'setono_sylius_cms.source.product_associations', of: ProductInterface::class, needs: ['product'], params: ProductAssociationsParams::class)]
final readonly class ProductAssociationsSource implements SourceInterface
{
    public const CODE = 'product_associations';

    /**
     * @param class-string $productAssociationClass
     */
    public function __construct(
        private VisibleProductsQueryInterface $visibleProducts,
        private string $productAssociationClass,
    ) {
    }

    public function resolve(object $params, RenderContext $context): AdapterInterface
    {
        if (!$params instanceof ProductAssociationsParams) {
            throw new \InvalidArgumentException(sprintf('The %s source takes %s, got %s', self::CODE, ProductAssociationsParams::class, $params::class));
        }

        $product = $context->get('product');
        if (!$product instanceof ProductInterface) {
            return new ArrayAdapter([]);
        }

        // A subquery rather than a join, so a product in several of the product's associations is listed once
        $associated = sprintf('SELECT cms_source_association.id FROM %s cms_source_association INNER JOIN cms_source_association.associatedProducts cms_source_associated', $this->productAssociationClass);
        $conditions = 'cms_source_association.owner = :cms_source_owner AND cms_source_associated.id = product.id';
        if (null !== $params->associationType) {
            $associated .= ' INNER JOIN cms_source_association.type cms_source_association_type';
            $conditions .= ' AND cms_source_association_type.code = :cms_source_association_type';
        }

        $queryBuilder = $this->visibleProducts->createQueryBuilder($context)
            ->andWhere(sprintf('EXISTS (%s WHERE %s)', $associated, $conditions))
            ->setParameter('cms_source_owner', $product)
            ->addOrderBy('product.id', 'ASC')
        ;

        if (null !== $params->associationType) {
            $queryBuilder->setParameter('cms_source_association_type', $params->associationType);
        }

        // No collection is fetched with the products, so the paginator pages the query as it is
        return new QueryAdapter($queryBuilder, false);
    }
}
