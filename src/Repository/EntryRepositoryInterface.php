<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Doctrine\ORM\QueryBuilder;
use Pagerfanta\Pagerfanta;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Problem\ProblemCountProviderInterface;

/**
 * Finds entries of any content type. It's the repository of the setono_sylius_cms.entry resource, whose grids are the
 * admin's entry lists
 */
interface EntryRepositoryInterface
{
    public function findOneByUuid(string $uuid): ?EntryInterface;

    /**
     * The entries with these UUIDs, of any content type
     *
     * @param list<string> $uuids
     *
     * @return list<EntryInterface>
     */
    public function findByUuids(array $uuids): array;

    /**
     * Those of the UUIDs that an entry has
     *
     * @param list<string> $uuids
     *
     * @return list<string>
     */
    public function findExistingUuids(array $uuids): array;

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
     * The entries whose date window starts after $now, the first to start first, which the Overview's Coming up lists:
     * those enabled, and published or with a publish that hasn't failed scheduled for their start or before, as only
     * their start changes what shoppers see
     *
     * @return list<EntryInterface>
     */
    public function findStartingAfter(\DateTimeImmutable $now, int $limit): array;

    /**
     * The entries whose date window ends after $now, the first to end first, which the Overview's Coming up lists:
     * those enabled, and published or with a publish that hasn't failed scheduled for their end or before, as only
     * their end changes what shoppers see
     *
     * @return list<EntryInterface>
     */
    public function findEndingAfter(\DateTimeImmutable $now, int $limit): array;

    /**
     * The entries a draft is in progress for: those with a draft, which are published entries with unpublished changes,
     * and those never published, which are their own working copy. The one edited last first, which the Overview's
     * Drafts in progress lists
     *
     * @return list<EntryInterface>
     */
    public function findDraftsInProgress(int $limit): array;

    /**
     * The entries the lists' Needs attention filter finds, those with an error or a warning by the counts stored with
     * each, the one edited last first, which the Overview's Needs attention pages through
     *
     * @return Pagerfanta<EntryInterface>
     */
    public function createNeedingAttentionPaginator(ProblemCountProviderInterface $problemCounts): Pagerfanta;

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
