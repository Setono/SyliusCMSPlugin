<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * Finds entries of any content type
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
}
