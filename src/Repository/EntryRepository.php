<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Doctrine\ORM\EntityRepository;
use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * @extends EntityRepository<EntryInterface>
 */
class EntryRepository extends EntityRepository implements EntryRepositoryInterface
{
    public function findOneByUuid(string $uuid): ?EntryInterface
    {
        $entry = $this->findOneBy(['uuid' => $uuid]);

        return $entry instanceof EntryInterface ? $entry : null;
    }
}
