<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin\Grid;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use Psr\Clock\ClockInterface;
use Setono\SyliusCMSPlugin\Admin\EntryStatusFilter;
use Setono\SyliusCMSPlugin\Model\DraftInterface;
use Setono\SyliusCMSPlugin\Problem\ProblemSummaryProviderInterface;
use Sylius\Bundle\GridBundle\Doctrine\DataSourceInterface as DoctrineDataSourceInterface;
use Sylius\Component\Grid\Data\DataSourceInterface;
use Sylius\Component\Grid\Filtering\FilterInterface;

/**
 * The grid filter type "setono_sylius_cms_entry_status": the entry lists' status filter, with the values of
 * EntryStatusFilter. It filters on the rows in SQL that MySQL and PostgreSQL both run. A status it doesn't know lets
 * every entry through, as Sylius's filters do with values they don't offer
 */
final readonly class StatusFilter implements FilterInterface
{
    public const TYPE = 'setono_sylius_cms_entry_status';

    public function __construct(
        private ClockInterface $clock,
        private ProblemSummaryProviderInterface $problemSummaries,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function apply(DataSourceInterface $dataSource, string $name, $data, array $options): void
    {
        $status = is_string($data) ? EntryStatusFilter::tryFrom($data) : null;
        if (null === $status || !$dataSource instanceof DoctrineDataSourceInterface) {
            return;
        }

        $queryBuilder = $dataSource->getQueryBuilder();
        if (!$queryBuilder instanceof QueryBuilder) {
            return;
        }

        $entry = $queryBuilder->getRootAliases()[0];

        match ($status) {
            EntryStatusFilter::NeedsAttention => $this->needsAttention($queryBuilder, $entry),
            EntryStatusFilter::UnpublishedChanges => $queryBuilder->andWhere(sprintf(
                'EXISTS (SELECT cms_status_draft.id FROM %s cms_status_draft WHERE cms_status_draft.entry = %s)',
                DraftInterface::class,
                $entry,
            )),
            EntryStatusFilter::NeverPublished => $queryBuilder->andWhere(sprintf('%s.lastPublishedAt IS NULL', $entry)),
            // "Show from" is inclusive and "Show until" exclusive. The columns hold UTC
            EntryStatusFilter::DisabledOrOutsideDateWindow => $queryBuilder
                ->andWhere(sprintf('%1$s.enabled = :cms_status_disabled OR %1$s.showFrom > :cms_status_now OR %1$s.showUntil <= :cms_status_now', $entry))
                ->setParameter('cms_status_disabled', false, Types::BOOLEAN)
                ->setParameter('cms_status_now', $this->clock->now()->setTimezone(new \DateTimeZone('UTC')), Types::DATETIME_IMMUTABLE),
        };
    }

    /**
     * The entries with a problem, which the problem summary finds outside SQL, by their IDs
     */
    private function needsAttention(QueryBuilder $queryBuilder, string $entry): void
    {
        $ids = $this->problemSummaries->getSummary()->entryIds();
        if ([] === $ids) {
            $queryBuilder->andWhere('1 = 0');

            return;
        }

        $queryBuilder->andWhere(sprintf('%s.id IN (:cms_status_needs_attention)', $entry))->setParameter('cms_status_needs_attention', $ids);
    }
}
