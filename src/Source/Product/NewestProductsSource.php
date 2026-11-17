<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Source\Product;

use Pagerfanta\Adapter\AdapterInterface;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Setono\SyliusCMSPlugin\Attribute\AsSource;
use Setono\SyliusCMSPlugin\Query\VisibleProductsQueryInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Source\SourceInterface;
use Sylius\Component\Core\Model\ProductInterface;

/**
 * "Newest": the products the shopper may see, newest first, then by their ID. See "Product sources" in
 * docs/spec/sources.md
 */
#[AsSource(code: self::CODE, label: 'setono_sylius_cms.source.newest_products', of: ProductInterface::class)]
final readonly class NewestProductsSource implements SourceInterface
{
    public const CODE = 'newest_products';

    public function __construct(private VisibleProductsQueryInterface $visibleProducts)
    {
    }

    public function resolve(object $params, RenderContext $context): AdapterInterface
    {
        $queryBuilder = $this->visibleProducts->createQueryBuilder($context)
            ->addOrderBy('product.createdAt', 'DESC')
            // A product created later in the same second has a higher ID
            ->addOrderBy('product.id', 'DESC')
        ;

        return new QueryAdapter($queryBuilder, false);
    }
}
