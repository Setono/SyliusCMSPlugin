<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Setono\SyliusCMSPlugin\Model\Homepage;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;

/**
 * The homepage resource's repository
 */
class HomepageRepository extends EntityRepository implements HomepageRepositoryInterface
{
    public function findByChannelCodes(array $channelCodes): array
    {
        if ([] === $channelCodes) {
            return [];
        }

        /** @var list<Homepage> $homepages */
        $homepages = $this->createQueryBuilder('o')
            ->addSelect('page')
            ->innerJoin('o.page', 'page')
            ->andWhere('o.channelCode IN (:channelCodes)')
            ->setParameter('channelCodes', $channelCodes)
            ->addOrderBy('o.channelCode', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        return $homepages;
    }
}
