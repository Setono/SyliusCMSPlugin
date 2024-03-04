<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Setono\SyliusCMSPlugin\Model\ElementInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Webmozart\Assert\Assert;

class ElementRepository extends EntityRepository implements ElementRepositoryInterface
{
    public function findOneByCode(string $code, string $channel = null, string $locale = null): ?ElementInterface
    {
        $qb = $this->createQueryBuilder('o')
            ->andWhere('o.code = :code')
            ->setParameter('code', $code)
        ;

        if (null !== $channel && $this->getClassMetadata()->hasAssociation('channels')) {
            // this has to be an inner join because we want to check if the element is available in the given channel
            $qb->innerJoin('o.channels', 'channel')
                ->andWhere('channel.code = :channel')
                ->setParameter('channel', $channel)
            ;
        }

        if (null !== $locale && $this->getClassMetadata()->hasAssociation('translations')) {
            // this has to be a left join because we want to join the translations, but still allow the element to be rendered if no translation is found
            $qb->addSelect('translation')
                ->leftJoin('o.translations', 'translation', 'WITH', 'translation.locale = :locale')
                ->setParameter('locale', $locale)
            ;
        }

        $obj = $qb
            ->getQuery()
            ->getOneOrNullResult()
        ;

        Assert::nullOrIsInstanceOf($obj, ElementInterface::class);

        return $obj;
    }
}
