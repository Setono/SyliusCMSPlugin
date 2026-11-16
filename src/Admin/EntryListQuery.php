<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Clock\ClockInterface;
use Setono\SyliusCMSPlugin\Doctrine\EntityManagers;
use Setono\SyliusCMSPlugin\Model\DraftInterface;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryChannelInterface;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;

/**
 * Filters in SQL that MySQL and PostgreSQL both run. Its subqueries' aliases and its parameters start with "cms_list_"
 */
final readonly class EntryListQuery implements EntryListQueryInterface
{
    public function __construct(
        private ManagerRegistry $managerRegistry,
        private ContentTypeRegistryInterface $contentTypes,
        private ClockInterface $clock,
    ) {
    }

    public function createQueryBuilder(?string $contentType, EntryListCriteria $criteria): QueryBuilder
    {
        // The translations are fetched with the entries, which the rows read their titles and slugs from
        $queryBuilder = $this->entries($contentType)
            ->select('entry', 'translation')
            ->leftJoin('entry.translations', 'translation')
            ->orderBy('entry.lastEditedAt', 'DESC')
            ->addOrderBy('entry.id', 'DESC')
        ;

        $search = trim((string) $criteria->search);
        if ('' !== $search) {
            $queryBuilder
                ->andWhere(sprintf(
                    'EXISTS (SELECT cms_list_search.id FROM %s cms_list_search WHERE cms_list_search.translatable = entry'
                    . ' AND (LOWER(cms_list_search.title) LIKE :cms_list_search OR LOWER(cms_list_search.slug) LIKE :cms_list_search))',
                    EntryTranslation::class,
                ))
                ->setParameter('cms_list_search', '%' . addcslashes(mb_strtolower($search), '%_\\') . '%')
            ;
        }

        if (null !== $criteria->status) {
            $this->filterOnStatus($queryBuilder, $criteria->status);
        }

        if (null !== $criteria->channel) {
            $queryBuilder
                ->andWhere(sprintf(
                    'EXISTS (SELECT cms_list_channel.id FROM %s cms_list_channel WHERE cms_list_channel.entry = entry AND cms_list_channel.code = :cms_list_channel)',
                    EntryChannelInterface::class,
                ))
                ->setParameter('cms_list_channel', $criteria->channel)
            ;
        }

        return $queryBuilder;
    }

    public function count(?string $contentType): int
    {
        return (int) $this->entries($contentType)->select('COUNT(entry.id)')->getQuery()->getSingleScalarResult();
    }

    private function filterOnStatus(QueryBuilder $queryBuilder, EntryStatusFilter $status): void
    {
        match ($status) {
            EntryStatusFilter::UnpublishedChanges => $queryBuilder->andWhere(sprintf(
                'EXISTS (SELECT cms_list_draft.id FROM %s cms_list_draft WHERE cms_list_draft.entry = entry)',
                DraftInterface::class,
            )),
            EntryStatusFilter::NeverPublished => $queryBuilder->andWhere('entry.lastPublishedAt IS NULL'),
            // "Show from" is inclusive and "Show until" exclusive. The columns hold UTC
            EntryStatusFilter::DisabledOrOutsideDateWindow => $queryBuilder
                ->andWhere('entry.enabled = :cms_list_disabled OR entry.showFrom > :cms_list_now OR entry.showUntil <= :cms_list_now')
                ->setParameter('cms_list_disabled', false, Types::BOOLEAN)
                ->setParameter('cms_list_now', $this->clock->now()->setTimezone(new \DateTimeZone('UTC')), Types::DATETIME_IMMUTABLE),
        };
    }

    /**
     * The entries of the content type, or of every content type when it's null, selected as "entry"
     */
    private function entries(?string $contentType): QueryBuilder
    {
        $modelClass = null === $contentType ? Entry::class : $this->contentTypes->get($contentType)->modelClass;

        return EntityManagers::of($this->managerRegistry, $modelClass)->createQueryBuilder()->from($modelClass, 'entry');
    }
}
