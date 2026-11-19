<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Setono\SyliusCMSPlugin\Model\ImportInterface;

/**
 * The imports started in the admin. See "Transfer" in docs/spec/admin.md
 */
interface ImportRepositoryInterface
{
    public function findOneByUuid(string $uuid): ?ImportInterface;

    /**
     * The latest imports, newest first, which the Transfer screen lists
     *
     * @return list<ImportInterface>
     */
    public function findLatest(int $limit): array;
}
