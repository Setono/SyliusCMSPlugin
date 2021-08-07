<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Setono\SyliusCMSPlugin\Model\TemplateInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

/**
 * @method TemplateInterface|null find($id, $lockMode = null, $lockVersion = null)
 * @method TemplateInterface|null findOneBy(array $criteria, array $orderBy = null)
 * @method TemplateInterface[]    findAll()
 * @method TemplateInterface[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
interface TemplateRepositoryInterface extends RepositoryInterface
{
    public function exists(string $code): bool;

    public function findOneByCode(string $code): ?TemplateInterface;
}
