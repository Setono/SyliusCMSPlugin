<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Doctrine\ORM;

use Setono\SyliusCMSPlugin\Model\TagInterface;
use Setono\SyliusCMSPlugin\Repository\TagRepositoryInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Webmozart\Assert\Assert;

class TagRepository extends EntityRepository implements TagRepositoryInterface
{
    public function findByCodePart(?string $phrase, ?int $limit = null): array
    {
        if (null === $phrase) {
            return [];
        }

        $tags = $this->createQueryBuilder('o')
            ->andWhere('o.code LIKE :code')
            ->setParameter('code', '%' . $phrase . '%')
            ->setMaxResults($limit ?? 25)
            ->getQuery()
            ->getResult()
        ;

        Assert::allIsInstanceOf($tags, TagInterface::class);

        return $tags;
    }
}
