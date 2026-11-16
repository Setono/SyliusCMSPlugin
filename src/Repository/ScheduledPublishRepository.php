<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\ScheduledPublishInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;

/**
 * The scheduled publish resource's repository
 */
class ScheduledPublishRepository extends EntityRepository implements ScheduledPublishRepositoryInterface
{
    public function findOneByEntry(EntryInterface $entry): ?ScheduledPublishInterface
    {
        $scheduledPublish = $this->findOneBy(['entry' => $entry]);

        return $scheduledPublish instanceof ScheduledPublishInterface ? $scheduledPublish : null;
    }

    public function findByEntries(array $entries): array
    {
        if ([] === $entries) {
            return [];
        }

        /** @var list<ScheduledPublishInterface> $scheduledPublishes */
        $scheduledPublishes = $this->createQueryBuilder('scheduled_publish')
            ->andWhere('scheduled_publish.entry IN (:entries)')
            ->setParameter('entries', $entries)
            ->getQuery()
            ->getResult()
        ;

        return $scheduledPublishes;
    }
}
