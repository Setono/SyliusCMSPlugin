<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\ShareLinkInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

/**
 * The share links of entries. add() stores one and remove() revokes it. See "Share links" in docs/spec/publishing.md
 *
 * @extends RepositoryInterface<ShareLinkInterface>
 */
interface ShareLinkRepositoryInterface extends RepositoryInterface
{
    public function findOneById(int $id): ?ShareLinkInterface;

    /**
     * The share link whose token this is, or null when there's none, such as for a revoked one
     */
    public function findOneByToken(string $token): ?ShareLinkInterface;

    /**
     * The entry's share links, newest first
     *
     * @return list<ShareLinkInterface>
     */
    public function findByEntry(EntryInterface $entry): array;
}
