<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * Who has which entry open in the builder
 */
interface EntryPresenceRepositoryInterface
{
    /**
     * Records that the admin user has the entry open now, and writes it at once
     *
     * @param string $editor the admin user's identifier
     * @param string $name their name, as the builder shows it
     */
    public function see(EntryInterface $entry, string $editor, string $name, \DateTimeImmutable $seenAt): void;

    /**
     * The names of the admin users seen with the entry open since the time, other than the one given, by name
     *
     * @return list<string>
     */
    public function findNamesSeenSince(EntryInterface $entry, \DateTimeImmutable $since, string $exceptEditor): array;

    /**
     * Forgets who was last seen with the entry open before the time
     */
    public function removeSeenBefore(EntryInterface $entry, \DateTimeImmutable $before): void;
}
