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

    public function findOneByToken(string $token): ?ShareLinkInterface
    {
        $shareLink = $this->findOneBy(['token' => $token]);

        return $shareLink instanceof ShareLinkInterface ? $shareLink : null;
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
