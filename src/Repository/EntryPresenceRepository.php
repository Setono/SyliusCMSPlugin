<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\EntryPresenceInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;

/**
 * The entry presence resource's repository
 */
class EntryPresenceRepository extends EntityRepository implements EntryPresenceRepositoryInterface
{
    public function see(EntryInterface $entry, string $editor, string $name, \DateTimeImmutable $seenAt): void
    {
        $presence = $this->findOneBy(['entry' => $entry, 'editor' => $editor]);
        if ($presence instanceof EntryPresenceInterface) {
            $presence->see($name, $seenAt);
        } else {
            // The resource's model, which a project can override
            $class = $this->getClassName();
            $presence = new $class($entry, $editor, $name, $seenAt);
            $this->getEntityManager()->persist($presence);
        }

        $this->getEntityManager()->flush();
    }

    public function findNamesSeenSince(EntryInterface $entry, \DateTimeImmutable $since, string $exceptEditor): array
    {
        /** @var list<array{name: string}> $rows */
        $rows = $this->createQueryBuilder('o')
            ->select('o.editor, o.name')
            ->andWhere('o.entry = :entry')
            ->andWhere('o.seenAt >= :since')
            ->andWhere('o.editor != :editor')
            ->setParameter('entry', $entry)
            ->setParameter('since', $since)
            ->setParameter('editor', $exceptEditor)
            ->groupBy('o.editor, o.name')
            ->orderBy('o.name', 'ASC')
            ->getQuery()
            ->getArrayResult()
        ;

        return array_map(static fn (array $row): string => $row['name'], $rows);
    }

    public function removeSeenBefore(EntryInterface $entry, \DateTimeImmutable $before): void
    {
        $this->createQueryBuilder('o')
            ->delete()
            ->andWhere('o.entry = :entry')
            ->andWhere('o.seenAt < :before')
            ->setParameter('entry', $entry)
            ->setParameter('before', $before)
            ->getQuery()
            ->execute()
        ;
    }
}
