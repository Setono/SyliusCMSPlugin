<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Collection;

use Doctrine\ORM\QueryBuilder;
use Setono\SyliusCMSPlugin\Model\EntryReferenceInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Filters queries of entries on the reference table, as listings and sources do, such as the posts in any of some
 * categories: an EXISTS on its indexed rows, which works on MySQL and PostgreSQL, and leaves each entry once however
 * many of the UUIDs it holds, so the query pages in SQL. The table mirrors published content only. See "Categories" in
 * docs/spec/collections.md
 */
final class ReferenceTable
{
    private function __construct()
    {
    }

    /**
     * The condition that the entry the alias names holds any of the UUIDs in the field, such as categories, or in any
     * indexed field when the field is null. It sets its parameters on the query builder, with names of their own, and
     * the caller adds it, such as with andWhere(). What isn't a UUID is left out, and no UUIDs is a condition no entry
     * meets
     *
     * @param string $alias the alias of the entries in the query, such as entry
     * @param list<string> $uuids
     */
    public static function holding(QueryBuilder $queryBuilder, string $alias, ?string $field, array $uuids): string
    {
        $uuids = array_values(array_unique(array_map(strtolower(...), array_filter($uuids, Uuid::isValid(...)))));
        if ([] === $uuids) {
            return '1 = 0';
        }

        $name = sprintf('cms_reference_%d', count($queryBuilder->getParameters()));
        $queryBuilder->setParameter($name . '_uuids', $uuids);

        $fieldCondition = '';
        if (null !== $field) {
            $fieldCondition = sprintf(' AND %1$s.field = :%1$s_field', $name);
            $queryBuilder->setParameter($name . '_field', $field);
        }

        return sprintf(
            'EXISTS (SELECT %1$s.id FROM %2$s %1$s WHERE %1$s.entry = %3$s AND %1$s.uuid IN (:%1$s_uuids)%4$s)',
            $name,
            EntryReferenceInterface::class,
            $alias,
            $fieldCondition,
        );
    }
}
