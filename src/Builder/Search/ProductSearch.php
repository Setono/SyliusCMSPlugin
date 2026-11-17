<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\Search;

use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Setono\SyliusCMSPlugin\Doctrine\EntityManagers;
use Sylius\Component\Core\Model\ProductInterface;

/**
 * Searches the store's products by their name in any locale and by their code, ignoring case, enabled or not: an
 * editor may link to a product before it's out. Each is named in the admin user's locale, and they're listed by that
 * name
 *
 * @internal
 */
final readonly class ProductSearch implements SearchInterface
{
    /** The most results it answers */
    public const LIMIT = 20;

    /**
     * @param class-string $productClass
     * @param class-string $productTranslationClass
     */
    public function __construct(
        private ManagerRegistry $managerRegistry,
        private string $productClass,
        private string $productTranslationClass,
    ) {
    }

    public function search(string $query, string $localeCode): array
    {
        $products = $this->products($localeCode)
            ->andWhere(sprintf(
                'LOWER(product.code) LIKE :query %2$s OR EXISTS (SELECT matched.id FROM %1$s matched WHERE matched.translatable = product AND LOWER(matched.name) LIKE :query %2$s)',
                $this->productTranslationClass,
                SearchPattern::ESCAPE,
            ))
            ->setParameter('query', SearchPattern::containing($query))
            ->setMaxResults(self::LIMIT)
        ;

        return $this->results($products, $localeCode);
    }

    public function find(array $refs, string $localeCode): array
    {
        if ([] === $refs) {
            return [];
        }

        return $this->results($this->products($localeCode)->andWhere('product.code IN (:codes)')->setParameter('codes', $refs), $localeCode);
    }

    /**
     * The products, by their name in the locale and then their code
     */
    private function products(string $localeCode): QueryBuilder
    {
        return EntityManagers::of($this->managerRegistry, $this->productClass)->createQueryBuilder()
            ->select('product')
            ->from($this->productClass, 'product')
            ->leftJoin('product.translations', 'named', 'WITH', 'named.locale = :locale')
            ->setParameter('locale', $localeCode)
            ->addOrderBy('named.name')
            ->addOrderBy('product.code')
        ;
    }

    /**
     * @return list<SearchResult>
     */
    private function results(QueryBuilder $queryBuilder, string $localeCode): array
    {
        /** @var list<ProductInterface> $products */
        $products = $queryBuilder->getQuery()->getResult();

        return array_map(static function (ProductInterface $product) use ($localeCode): SearchResult {
            $code = (string) $product->getCode();

            return new SearchResult($code, (string) ($product->getTranslation($localeCode)->getName() ?? $code));
        }, $products);
    }
}
