<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Doctrine\ORM;

use Setono\SyliusCMSPlugin\Model\TemplateInterface;
use Setono\SyliusCMSPlugin\Repository\TemplateRepositoryInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;

class TemplateRepository extends EntityRepository implements TemplateRepositoryInterface
{
    public function findOneByCode(string $code): ?TemplateInterface
    {
        return $this->findOneBy([
            'code' => $code
        ]);
    }

    public function exists(string $code): bool
    {
        return (int) $this->createQueryBuilder('o')
                ->select('COUNT(o)')
                ->andWhere('o.code = :code')
                ->setParameter('code', $code)
                ->getQuery()
                ->getSingleScalarResult() > 0
            ;
    }
}
