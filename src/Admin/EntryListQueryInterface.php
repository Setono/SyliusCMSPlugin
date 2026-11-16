<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Doctrine\ORM\QueryBuilder;
use Setono\SyliusCMSPlugin\Registry\UnknownContentTypeException;

/**
 * The entries of the admin's lists: All entries, and each content type's. It filters on the rows, so a draft's values,
 * such as a changed title, can't be searched (ADR 0009). See "Entry lists" in docs/spec/admin.md
 */
interface EntryListQueryInterface
{
    /**
     * The entries of the content type, or of every content type when it's null, that match the criteria, the ones
     * edited last first. The entries are selected as "entry"
     *
     * @throws UnknownContentTypeException
     */
    public function createQueryBuilder(?string $contentType, EntryListCriteria $criteria): QueryBuilder;

    /**
     * How many entries the content type has, or every content type when it's null
     *
     * @throws UnknownContentTypeException
     */
    public function count(?string $contentType): int;
}
