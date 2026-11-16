<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Doctrine\ORM\QueryBuilder;
use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * Finds entries of any content type. It's the repository of the setono_sylius_cms.entry resource, whose grids are the
 * admin's entry lists
 */
interface EntryRepositoryInterface
{
    public function findOneByUuid(string $uuid): ?EntryInterface;

    /**
     * The entry with this id. One the entity manager has loaded already is answered without a query
     */
    public function findOneById(int $id): ?EntryInterface;

    /**
     * Every entry, one at a time, so a caller going through all of them can clear the entity manager as it goes
     *
     * @return iterable<EntryInterface>
     */
    public function iterateAll(): iterable;

    /**
     * The entries of an admin list: of the content type, or of every content type when it's null. Entries are "o", and
     * the grids' filters reach their translations as "translation". The translations and audience groups are fetched
     * with them, for the rows (see "Entry lists" in docs/spec/admin.md)
     *
     * @param string|null $contentType a content type code
     */
    public function createListQueryBuilder(?string $contentType = null): QueryBuilder;

    /**
     * How many entries the content type has, or every content type when it's null
     *
     * @param string|null $contentType a content type code
     */
    public function countEntries(?string $contentType = null): int;

    /**
     * The entries that may name one of the customer groups in their audience or in a customer group condition, in
     * their rows or their draft. As a draft is JSON, it's the entries whose rows or draft hold a code anywhere, which
     * can be more than those that name it
     *
     * @param list<string> $codes
     *
     * @return list<EntryInterface>
     */
    public function findByCustomerGroupCodes(array $codes): array;

    /**
     * The entries that may refer to one of the records, by their codes or UUIDs, in their rows' block tree or their
     * draft. As both are JSON, it's the entries that hold one of them as a JSON string anywhere, which can be more than
     * those that refer to it
     *
     * @param list<string> $ids
     *
     * @return list<EntryInterface>
     */
    public function findReferencing(array $ids): array;
}
