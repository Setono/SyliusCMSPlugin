<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Doctrine\ORM\EntityRepository;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\EntryPresence;

/**
 * @extends EntityRepository<EntryPresence>
 */
class EntryPresenceRepository extends EntityRepository implements EntryPresenceRepositoryInterface
{
    public function see(EntryInterface $entry, string $editor, string $name, \DateTimeImmutable $seenAt): void
    {
        if (!$entry instanceof Entry) {
            throw new \InvalidArgumentException(sprintf('Presence belongs to an entry, which extends %s', Entry::class));
        }

        $presence = $this->findOneBy(['entry' => $entry, 'editor' => $editor]);
        if ($presence instanceof EntryPresence) {
            $presence->see($name, $seenAt);
        } else {
            $presence = new EntryPresence($entry, $editor, $name, $seenAt);
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
