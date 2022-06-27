<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Setono\SyliusCMSPlugin\Model\AssetInterface;

/**
 * @method AssetInterface|null find($id, $lockMode = null, $lockVersion = null)
 * @method AssetInterface|null findOneBy(array $criteria, array $orderBy = null)
 * @method AssetInterface[]    findAll()
 * @method AssetInterface[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
interface AssetRepositoryInterface extends ElementRepositoryInterface
{
}
