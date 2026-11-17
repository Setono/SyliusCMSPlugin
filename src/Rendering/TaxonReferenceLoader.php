<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

use Doctrine\Persistence\ManagerRegistry;
use Setono\SyliusCMSPlugin\Doctrine\EntityManagers;
use Setono\SyliusCMSPlugin\Query\VisibleTaxonsQueryInterface;
use Sylius\Component\Core\Model\TaxonInterface;

/**
 * Loads the taxons references name, by code: those the shopper may see, through the visible taxons query, so a link to
 * a disabled taxon isn't rendered. See "Visible queries" in docs/spec/sources.md
 *
 * @internal
 */
final readonly class TaxonReferenceLoader implements ReferenceTypeLoaderInterface
{
    /**
     * @param class-string $taxonClass
     */
    public function __construct(
        private VisibleTaxonsQueryInterface $visibleTaxons,
        private ManagerRegistry $managerRegistry,
        private string $taxonClass,
    ) {
    }

    public function load(array $ids, RenderContext $context): array
    {
        /** @var list<TaxonInterface> $taxons */
        $taxons = $this->visibleTaxons->createQueryBuilder($context)
            ->andWhere('taxon.code IN (:cms_codes)')
            ->setParameter('cms_codes', $ids)
            ->getQuery()
            ->getResult()
        ;

        $loaded = [];
        foreach ($taxons as $taxon) {
            $loaded[(string) $taxon->getCode()] = $taxon;
        }

        return $loaded;
    }

    public function existing(array $ids): array
    {
        /** @var list<string> $codes */
        $codes = EntityManagers::of($this->managerRegistry, $this->taxonClass)->createQueryBuilder()
            ->select('taxon.code')
            ->from($this->taxonClass, 'taxon')
            ->andWhere('taxon.code IN (:codes)')
            ->setParameter('codes', $ids)
            ->getQuery()
            ->getSingleColumnResult()
        ;

        return $codes;
    }
}
