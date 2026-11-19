<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Repository;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\ScheduledPublishInterface;

/**
 * The entries' scheduled publishes, at most one per entry. See "Scheduled publishes" in docs/spec/publishing.md
 */
interface ScheduledPublishRepositoryInterface
{
    public function findOneByEntry(EntryInterface $entry): ?ScheduledPublishInterface;

    /**
     * The scheduled publishes of these entries, in one query, for the entries that have one
     *
     * @param list<EntryInterface> $entries
     *
     * @return list<ScheduledPublishInterface>
     */
    public function findByEntries(array $entries): array;

    /**
     * The scheduled publishes that wait and aren't overdue at $now, earliest first, with their entries, which the
     * Overview's Coming up lists. A failed or overdue one needs attention instead
     *
     * @param list<string> $hiddenContentTypes the codes of the content types switched off, whose entries are left out
     *
     * @return list<ScheduledPublishInterface>
     */
    public function findComingUp(\DateTimeImmutable $now, int $limit, array $hiddenContentTypes = []): array;
}
