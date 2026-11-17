<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Source\Product;

use Doctrine\ORM\QueryBuilder;
use Sylius\Component\Core\Model\TaxonInterface;

/**
 * Narrows a visible-products query (root alias "product") to the products in a taxon, or in it or any taxon below it,
 * each once, and orders it by their position there, in SQL that MySQL and PostgreSQL both run. The product sources with
 * a taxon parameter share it, such as "In taxon". Its own aliases and parameters start with "cms_source_"
 *
 * @internal
 */
final readonly class TaxonFilter
{
    /** The alias of the product's place in the taxon, joined for a taxon without its children */
    private const PRODUCT_TAXON = 'cms_source_product_taxon';

    /**
     * @param class-string $productTaxonClass
     */
    public function __construct(private string $productTaxonClass)
    {
    }

    /**
     * Keeps the products in the taxon or, with $includeChildren, in it or any taxon below it. Without children it joins
     * the product's one place in the taxon, and with them it checks the subtree in a subquery, so a product in several
     * of its taxons is still listed once
     */
    public function keepInTaxon(QueryBuilder $queryBuilder, TaxonInterface $taxon, bool $includeChildren): void
    {
        if (!$includeChildren) {
            $queryBuilder
                ->innerJoin('product.productTaxons', self::PRODUCT_TAXON, 'WITH', sprintf('%s.taxon = :cms_source_taxon', self::PRODUCT_TAXON))
                ->setParameter('cms_source_taxon', $taxon)
            ;

            return;
        }

        $queryBuilder->andWhere(sprintf(
            'EXISTS (SELECT cms_source_in_subtree.id FROM %s cms_source_in_subtree INNER JOIN cms_source_in_subtree.taxon cms_source_subtree_taxon WHERE cms_source_in_subtree.product = product AND %s)',
            $this->productTaxonClass,
            self::inSubtree('cms_source_subtree_taxon'),
        ));
        self::setSubtreeParameters($queryBuilder, $taxon);
    }

    /**
     * Orders the products keepInTaxon() kept by their position in the taxon or, with $includeChildren, by their lowest
     * position in it or any taxon below it. A caller adds the ID after it, as the last tie-breaker
     */
    public function orderByPosition(QueryBuilder $queryBuilder, TaxonInterface $taxon, bool $includeChildren): void
    {
        if (!$includeChildren) {
            $queryBuilder->addOrderBy(sprintf('%s.position', self::PRODUCT_TAXON), 'ASC');

            return;
        }

        $queryBuilder
            ->addSelect(sprintf(
                '(SELECT MIN(cms_source_position_in_subtree.position) FROM %s cms_source_position_in_subtree INNER JOIN cms_source_position_in_subtree.taxon cms_source_position_taxon WHERE cms_source_position_in_subtree.product = product AND %s) AS HIDDEN cms_source_position',
                $this->productTaxonClass,
                self::inSubtree('cms_source_position_taxon'),
            ))
            ->addOrderBy('cms_source_position', 'ASC')
        ;
        self::setSubtreeParameters($queryBuilder, $taxon);
    }

    /**
     * The condition that the taxon with the alias is the taxon or below it, in Sylius's nested set
     */
    private static function inSubtree(string $alias): string
    {
        return sprintf('%1$s.root = :cms_source_taxon_root AND %1$s.left >= :cms_source_taxon_left AND %1$s.right <= :cms_source_taxon_right', $alias);
    }

    private static function setSubtreeParameters(QueryBuilder $queryBuilder, TaxonInterface $taxon): void
    {
        $queryBuilder
            // A root taxon is its own root
            ->setParameter('cms_source_taxon_root', $taxon->getRoot() ?? $taxon)
            ->setParameter('cms_source_taxon_left', $taxon->getLeft())
            ->setParameter('cms_source_taxon_right', $taxon->getRight())
        ;
    }
}
