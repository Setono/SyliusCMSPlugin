<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Setono\SyliusCMSPlugin\Model\TemplateInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

interface TemplateRepositoryInterface extends RepositoryInterface
{
    public function exists(string $code): bool;

    public function findOneByCode(string $code): ?TemplateInterface;
}
