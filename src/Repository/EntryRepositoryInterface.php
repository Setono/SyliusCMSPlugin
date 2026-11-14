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
}
