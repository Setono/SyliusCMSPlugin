<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Doctrine\DBAL\Types\Types;
use Setono\SyliusCMSPlugin\Model\Entry;
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

    public function findComingUp(\DateTimeImmutable $now, int $limit, array $hiddenContentTypes = []): array
    {
        // Overdue at OVERDUE_AFTER past its time. The column holds UTC
        $overdueAfter = (new \DateTimeImmutable('@0'))->modify(ScheduledPublishInterface::OVERDUE_AFTER)->getTimestamp();

        $queryBuilder = $this->createQueryBuilder('scheduled_publish')
            ->addSelect('entry')
            ->innerJoin('scheduled_publish.entry', 'entry')
        ;

        /** @var list<ScheduledPublishInterface> $scheduledPublishes */
        $scheduledPublishes = HiddenContentTypes::exclude($queryBuilder, 'entry', $this->getEntityManager()->getClassMetadata(Entry::class), $hiddenContentTypes)
            ->andWhere('scheduled_publish.failureReason IS NULL')
            ->andWhere('scheduled_publish.publishAt > :overdue_before')
            ->setParameter('overdue_before', $now->setTimezone(new \DateTimeZone('UTC'))->modify(sprintf('-%d seconds', $overdueAfter)), Types::DATETIME_IMMUTABLE)
            ->addOrderBy('scheduled_publish.publishAt', 'ASC')
            ->addOrderBy('scheduled_publish.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;

        return $scheduledPublishes;
    }
}
