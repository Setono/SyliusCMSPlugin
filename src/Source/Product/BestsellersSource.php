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
 * "Bestsellers": the products the shopper may see that have a score in the channel, highest first, then by their ID,
 * optionally only those in a taxon, or in it or any taxon below it. It joins the scores
 * setono:cms:bestsellers:compute last stored, so a product without one is left out, and the list is empty until the
 * command first runs. The join, the taxon filter and the order are SQL, so a list never comes up short while enough
 * products the shopper may see are scored. See "Product sources" and "Bestsellers" in docs/spec/sources.md
 */
#[AsSource(code: self::CODE, label: 'setono_sylius_cms.source.bestsellers', of: ProductInterface::class, params: BestsellersParams::class)]
final readonly class BestsellersSource implements SourceInterface
{
    public const CODE = 'bestsellers';

    /**
     * @param class-string $scoreClass the bestseller score model
     */
    public function __construct(
        private VisibleProductsQueryInterface $visibleProducts,
        private TaxonFilter $taxonFilter,
        private string $scoreClass,
    ) {
    }

    public function resolve(object $params, RenderContext $context): AdapterInterface
    {
        if (!$params instanceof BestsellersParams) {
            throw new \InvalidArgumentException(sprintf('The %s source takes %s, got %s', self::CODE, BestsellersParams::class, $params::class));
        }

        // A channel has one score per product, so each product joins one row at most
        $queryBuilder = $this->visibleProducts->createQueryBuilder($context)
            ->innerJoin($this->scoreClass, 'cms_source_score', 'WITH', 'cms_source_score.productCode = product.code AND cms_source_score.channelCode = :cms_source_score_channel_code')
            ->setParameter('cms_source_score_channel_code', $context->channel->getCode())
        ;

        if (null !== $params->taxon) {
            $this->taxonFilter->keepInTaxon($queryBuilder, $params->taxon, $params->includeChildren);
        }

        $queryBuilder
            ->addOrderBy('cms_source_score.score', 'DESC')
            ->addOrderBy('product.id', 'ASC')
        ;

        // No collection is fetched with the products, so the paginator pages the query as it is
        return new QueryAdapter($queryBuilder, false);
    }
}
