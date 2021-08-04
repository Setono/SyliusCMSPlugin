<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Setono\SyliusCMSPlugin\Model\ViewInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

interface ViewRepositoryInterface extends RepositoryInterface
{
    public function findOneByCode(string $code): ?ViewInterface;
}
