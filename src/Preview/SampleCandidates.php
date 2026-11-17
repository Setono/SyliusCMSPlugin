<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preview;

use Psr\Container\ContainerInterface;
use Setono\SyliusCMSPlugin\Builder\Search\SearchInterface;
use Setono\SyliusCMSPlugin\Builder\Search\SearchResult;
use Setono\SyliusCMSPlugin\Condition\ConditionMatcher;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Field\ReferenceType;
use Setono\SyliusCMSPlugin\Query\VisibleProductsQueryInterface;
use Setono\SyliusCMSPlugin\Rendering\ReferenceLoaderInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Source\Product\TaxonFilter;
use Sylius\Component\Core\Model\TaxonInterface;

/**
 * The candidates for a snippet preview's starting sample object, best first: the objects of the key the page scope
 * names, such as the products of "product is one of", then for products the first products in the taxons it names,
 * such as those of "product is in taxon", then the first page of the builder's search of the key
 *
 * @internal
 */
final readonly class SampleCandidates implements SampleCandidatesInterface
{
    /** The most products it takes from each taxon the scope names */
    private const PER_TAXON = 10;

    /**
     * @param ContainerInterface $searches the builder's searches by kind
     */
    public function __construct(
        private ConditionMatcher $conditions,
        private ReferenceLoaderInterface $referenceLoader,
        private VisibleProductsQueryInterface $visibleProducts,
        private TaxonFilter $taxonFilter,
        private ContainerInterface $searches,
    ) {
    }

    public function refs(string $key, array $pageScope, RenderContext $context): iterable
    {
        $references = $this->conditions->references($pageScope);

        foreach ($references as $reference) {
            if ($reference->type === $key) {
                yield $reference->id;
            }
        }

        if (ReferenceType::PRODUCT === $key) {
            yield from $this->productsInTaxons(array_values(array_filter($references, static fn (Reference $reference): bool => ReferenceType::TAXON === $reference->type)), $context);
        }

        if ($this->searches->has($key)) {
            $search = $this->searches->get($key);
            if ($search instanceof SearchInterface) {
                yield from array_map(static fn (SearchResult $result): string => $result->ref, $search->search('', $context->localeCode));
            }
        }
    }

    /**
     * @param list<Reference> $taxons
     *
     * @return \Generator<string>
     */
    private function productsInTaxons(array $taxons, RenderContext $context): \Generator
    {
        if ([] === $taxons) {
            return;
        }

        $loaded = $this->referenceLoader->load($taxons, $context);
        foreach ($taxons as $reference) {
            $taxon = $loaded->get(ReferenceType::TAXON, $reference->id);
            if (!$taxon instanceof TaxonInterface) {
                continue;
            }

            $queryBuilder = $this->visibleProducts->createQueryBuilder($context)
                ->select('product.code')
                ->addOrderBy('product.id')
                ->setMaxResults(self::PER_TAXON)
            ;
            $this->taxonFilter->keepInTaxon($queryBuilder, $taxon, true);

            /** @var list<string> $codes */
            $codes = $queryBuilder->getQuery()->getSingleColumnResult();

            yield from $codes;
        }
    }
}
