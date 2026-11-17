<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Source\Taxon;

use Pagerfanta\Adapter\AdapterInterface;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Setono\SyliusCMSPlugin\Attribute\AsSource;
use Setono\SyliusCMSPlugin\Query\VisibleTaxonsQueryInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Source\SourceInterface;
use Sylius\Component\Core\Model\TaxonInterface;

/**
 * "Children of a taxon": the taxons the shopper may see right below a taxon, by their position, then by their ID.
 * See "Taxon sources" in docs/spec/sources.md
 */
#[AsSource(code: self::CODE, label: 'setono_sylius_cms.source.taxon_children', of: TaxonInterface::class, params: TaxonChildrenParams::class)]
final readonly class TaxonChildrenSource implements SourceInterface
{
    public const CODE = 'taxon_children';

    public function __construct(private VisibleTaxonsQueryInterface $visibleTaxons)
    {
    }

    public function resolve(object $params, RenderContext $context): AdapterInterface
    {
        if (!$params instanceof TaxonChildrenParams) {
            throw new \InvalidArgumentException(sprintf('The %s source takes %s, got %s', self::CODE, TaxonChildrenParams::class, $params::class));
        }

        if (null === $params->taxon) {
            return new ArrayAdapter([]);
        }

        $queryBuilder = $this->visibleTaxons->createQueryBuilder($context)
            ->andWhere('taxon.parent = :cms_source_parent')
            ->setParameter('cms_source_parent', $params->taxon)
            ->addOrderBy('taxon.position', 'ASC')
            ->addOrderBy('taxon.id', 'ASC')
        ;

        return new QueryAdapter($queryBuilder, false);
    }
}
