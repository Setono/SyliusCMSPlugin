<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Setono\SyliusCMSPlugin\Model\DraftInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
interface DraftRepositoryInterface
{
    public function findOneByEntry(EntryInterface $entry): ?DraftInterface;
}
