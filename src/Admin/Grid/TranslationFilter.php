<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin\Grid;

use Doctrine\ORM\QueryBuilder;
use Setono\SyliusCMSPlugin\Query\CompletenessQueryInterface;
use Sylius\Bundle\GridBundle\Doctrine\DataSourceInterface as DoctrineDataSourceInterface;
use Sylius\Component\Grid\Data\DataSourceInterface;
use Sylius\Component\Grid\Filtering\FilterInterface;

/**
 * The grid filter type "setono_sylius_cms_entry_translation": the entry lists' translation filter, by locale code,
 * which keeps the entries that aren't complete in the locale, as the Overview's Translations links to them. It reads
 * the locales stored with each entry, in SQL
 */
final readonly class TranslationFilter implements FilterInterface
{
    public const TYPE = 'setono_sylius_cms_entry_translation';

    public function __construct(private CompletenessQueryInterface $completeness)
    {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function apply(DataSourceInterface $dataSource, string $name, $data, array $options): void
    {
        if (!is_string($data) || '' === $data || !$dataSource instanceof DoctrineDataSourceInterface) {
            return;
        }

        $queryBuilder = $dataSource->getQueryBuilder();
        if (!$queryBuilder instanceof QueryBuilder) {
            return;
        }

        $queryBuilder->andWhere($this->completeness->getIncompleteCondition($queryBuilder, $queryBuilder->getRootAliases()[0], $data));
    }
}
