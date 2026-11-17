<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\Search;

use Setono\SyliusCMSPlugin\Doctrine\SearchPattern;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Setono\SyliusCMSPlugin\Doctrine\EntityManagers;
use Sylius\Component\Core\Model\TaxonInterface;

/**
 * Searches the store's taxons by their name in any locale and by their code, ignoring case, enabled or not. Each is
 * named by its path in the admin user's locale, such as "Category / T-shirts", as Sylius's admin names them, and
 * they're listed in the tree's order
 *
 * @internal
 */
final readonly class TaxonSearch implements SearchInterface
{
    /** The most results it answers */
    public const LIMIT = 20;

    /**
     * @param class-string $taxonClass
     * @param class-string $taxonTranslationClass
     */
    public function __construct(
        private ManagerRegistry $managerRegistry,
        private string $taxonClass,
        private string $taxonTranslationClass,
    ) {
    }

    public function search(string $query, string $localeCode): array
    {
        $taxons = $this->taxons()
            ->andWhere(sprintf(
                'LOWER(taxon.code) LIKE :query %2$s OR EXISTS (SELECT matched.id FROM %1$s matched WHERE matched.translatable = taxon AND LOWER(matched.name) LIKE :query %2$s)',
                $this->taxonTranslationClass,
                SearchPattern::ESCAPE,
            ))
            ->setParameter('query', SearchPattern::containing($query))
            ->setMaxResults(self::LIMIT)
        ;

        return $this->results($taxons, $localeCode);
    }

    public function find(array $refs, string $localeCode): array
    {
        if ([] === $refs) {
            return [];
        }

        return $this->results($this->taxons()->andWhere('taxon.code IN (:codes)')->setParameter('codes', $refs), $localeCode);
    }

    private function taxons(): QueryBuilder
    {
        return EntityManagers::of($this->managerRegistry, $this->taxonClass)->createQueryBuilder()
            ->select('taxon')
            ->from($this->taxonClass, 'taxon')
            ->addOrderBy('taxon.left')
            ->addOrderBy('taxon.code')
        ;
    }

    /**
     * @return list<SearchResult>
     */
    private function results(QueryBuilder $queryBuilder, string $localeCode): array
    {
        /** @var list<TaxonInterface> $taxons */
        $taxons = $queryBuilder->getQuery()->getResult();

        return array_map(static fn (TaxonInterface $taxon): SearchResult => new SearchResult((string) $taxon->getCode(), self::path($taxon, $localeCode)), $taxons);
    }

    /**
     * The names of the taxon and its ancestors in the locale, from the root
     */
    private static function path(TaxonInterface $taxon, string $localeCode): string
    {
        $names = [];
        for ($current = $taxon; null !== $current; $current = $current->getParent()) {
            array_unshift($names, $current->getTranslation($localeCode)->getName() ?? $current->getCode());
        }

        return implode(' / ', $names);
    }
}
