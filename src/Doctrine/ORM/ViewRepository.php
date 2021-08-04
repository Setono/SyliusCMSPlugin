<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Doctrine\ORM;

use Setono\SyliusCMSPlugin\Model\ViewInterface;
use Setono\SyliusCMSPlugin\Repository\ViewRepositoryInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;

class ViewRepository extends EntityRepository implements ViewRepositoryInterface
{
    public function findOneByCode(string $code): ?ViewInterface
    {
        return $this->findOneBy([
            'code' => $code
        ]);
    }
}
