<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Setono\SyliusCMSPlugin\Model\BlockInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

interface BlockRepositoryInterface extends RepositoryInterface
{
    public function findOneByCode(string $code): ?BlockInterface;
}
