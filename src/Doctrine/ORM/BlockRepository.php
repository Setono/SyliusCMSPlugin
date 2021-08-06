<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Doctrine\ORM;

use Setono\SyliusCMSPlugin\Model\BlockInterface;
use Setono\SyliusCMSPlugin\Repository\BlockRepositoryInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;

class BlockRepository extends EntityRepository implements BlockRepositoryInterface
{
    public function findOneByCode(string $code): ?BlockInterface
    {
        return $this->findOneBy([
            'code' => $code
        ]);
    }
}
