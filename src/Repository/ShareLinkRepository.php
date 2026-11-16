<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\ShareLinkInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;

/**
 * The share link resource's repository. Share links made at the same time are ordered by id, the order they were stored in
 *
 * @extends EntityRepository<ShareLinkInterface>
 */
class ShareLinkRepository extends EntityRepository implements ShareLinkRepositoryInterface
{
    public function findOneById(int $id): ?ShareLinkInterface
    {
        $shareLink = $this->find($id);

        return $shareLink instanceof ShareLinkInterface ? $shareLink : null;
    }

    public function lookUpToken(string $token): ?array
    {
        /** @var array{entry: string, channel: string, locale: string, shownTo: string, date: \DateTimeImmutable|null}|null $shareLink */
        $shareLink = $this->createQueryBuilder('o')
            ->select('sharedEntry.uuid AS entry', 'o.channelCode AS channel', 'o.localeCode AS locale', 'o.shownTo AS shownTo', 'o.date AS date')
            ->join('o.entry', 'sharedEntry')
            ->andWhere('o.token = :token')
            ->setParameter('token', $token)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        if (null === $shareLink) {
            return null;
        }

        // Doctrine hydrates the column in PHP's default time zone, but it holds UTC
        $date = $shareLink['date'];
        $shareLink['date'] = null === $date ? null : new \DateTimeImmutable($date->format('Y-m-d H:i:s'), new \DateTimeZone('UTC'));

        return $shareLink;
    }

    public function findByEntry(EntryInterface $entry): array
    {
        /** @var list<ShareLinkInterface> $shareLinks */
        $shareLinks = $this->createQueryBuilder('o')
            ->andWhere('o.entry = :entry')
            ->setParameter('entry', $entry)
            ->addOrderBy('o.createdAt', 'DESC')
            ->addOrderBy('o.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        return $shareLinks;
    }
}
