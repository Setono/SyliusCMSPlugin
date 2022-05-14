<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Doctrine\ORM;

use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Repository\PageRepositoryInterface;
use Webmozart\Assert\Assert;

class PageRepository extends ElementRepository implements PageRepositoryInterface
{
    public function findOneBySlug(string $locale, string $slug): ?PageInterface
    {
        $obj = $this->createQueryBuilder('o')
            ->addSelect('translation')
            ->innerJoin('o.translations', 'translation', 'WITH', 'translation.locale = :locale')
            ->andWhere('translation.slug = :slug')
            ->setParameter('locale', $locale)
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        Assert::nullOrIsInstanceOf($obj, PageInterface::class);

        return $obj;
    }

    public function exists(string $slug): bool
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o)')
            ->leftJoin('o.translations', 'translation')
            ->andWhere('translation.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getSingleScalarResult() > 0
        ;
    }
}
