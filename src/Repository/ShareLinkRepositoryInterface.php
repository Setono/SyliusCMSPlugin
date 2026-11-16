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
     * What the share link whose token this is shows: its entry's UUID, its channel, its locale, its Shown to and its
     * Date (in UTC, or null for the time it's opened). Null when there's no such share link, such as for a revoked one.
     *
     * It reads them without loading the entry, because loading an entry asks Sylius's locale context for the entry's
     * locale, and the locale context asks the share link
     *
     * @return array{entry: string, channel: string, locale: string, shownTo: string, date: \DateTimeImmutable|null}|null
     */
    public function lookUpToken(string $token): ?array;

    /**
     * The entry's share links, newest first
     *
     * @return list<ShareLinkInterface>
     */
    public function findByEntry(EntryInterface $entry): array;
}
