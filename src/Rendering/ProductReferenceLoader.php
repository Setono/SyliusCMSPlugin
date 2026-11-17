<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

use Doctrine\Persistence\ManagerRegistry;
use Setono\SyliusCMSPlugin\Query\VisibleProductsQueryInterface;
use Sylius\Component\Core\Model\ProductInterface;

/**
 * Loads the products references name, by code: those the shopper may see, through the visible products query, so a
 * link to a disabled product, or to one outside the channel or not translated into the locale, isn't rendered.
 * See "Visible queries" in docs/spec/sources.md
 *
 * @internal
 */
final readonly class ProductReferenceLoader implements ReferenceTypeLoaderInterface
{
    /**
     * @param class-string $productClass
     */
    public function __construct(
        private VisibleProductsQueryInterface $visibleProducts,
        private ManagerRegistry $managerRegistry,
        private string $productClass,
    ) {
    }

    public function load(array $ids, RenderContext $context): array
    {
        /** @var list<ProductInterface> $products */
        $products = $this->visibleProducts->createQueryBuilder($context)
            ->andWhere('product.code IN (:cms_codes)')
            ->setParameter('cms_codes', $ids)
            ->getQuery()
            ->getResult()
        ;

        return CodedRecords::byCode($products);
    }

    public function existing(array $ids): array
    {
        return CodedRecords::existing($this->managerRegistry, $this->productClass, $ids);
    }
}
