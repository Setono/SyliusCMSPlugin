<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Pagerfanta\Adapter\AdapterInterface;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Setono\SyliusCMSPlugin\Attribute\AsSource;
use Setono\SyliusCMSPlugin\Query\VisibleProductsQueryInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Source\SourceInterface;
use Sylius\Component\Core\Model\ProductInterface;

/**
 * A project's source, as an agency writes one: the visible products whose code starts with FEATURED_, by code. Only
 * ListSourcesKernel registers it
 */
#[AsSource(code: 'featured', label: 'Featured', of: ProductInterface::class)]
final readonly class FeaturedProducts implements SourceInterface
{
    public function __construct(private VisibleProductsQueryInterface $visibleProducts)
    {
    }

    public function resolve(object $params, RenderContext $context): AdapterInterface
    {
        return new QueryAdapter(
            $this->visibleProducts->createQueryBuilder($context)
                ->andWhere('product.code LIKE :prefix')
                ->setParameter('prefix', 'FEATURED\_%')
                ->addOrderBy('product.code', 'ASC')
                ->addOrderBy('product.id', 'ASC'),
            false,
        );
    }
}
