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
}
