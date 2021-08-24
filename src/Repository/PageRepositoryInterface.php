<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Setono\SyliusCMSPlugin\Model\PageInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

/**
 * @method PageInterface|null find($id, $lockMode = null, $lockVersion = null)
 * @method PageInterface|null findOneBy(array $criteria, array $orderBy = null)
 * @method PageInterface[]    findAll()
 * @method PageInterface[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
interface PageRepositoryInterface extends RepositoryInterface
{
    public function findOneBySlug(string $locale, string $slug): ?PageInterface;
}
