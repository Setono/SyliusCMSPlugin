<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Psr\Clock\ClockInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\ScheduledPublishRepositoryInterface;

/**
 * The Overview's Coming up: the scheduled publishes that wait, and the date windows that start or end after now, in
 * time order. Each source gives its first ones in SQL, so the first ones of all of them are among those. An overdue
 * scheduled publish needs attention instead. See "Overview" in docs/spec/admin.md
 */
final readonly class ComingUp
{
    /** How many events the panel lists */
    public const LIMIT = 10;

    public function __construct(
        private ScheduledPublishRepositoryInterface $scheduledPublishes,
        private EntryRepositoryInterface $entries,
        private ClockInterface $clock,
        private int $limit = self::LIMIT,
    ) {
    }

    /**
     * @return list<ComingUpEvent> the first ones, earliest first, and at the same time a publish before a start before
     *                             an end
     */
    public function events(): array
    {
        $now = $this->clock->now();

        $events = [];
        foreach ($this->scheduledPublishes->findComingUp($now, $this->limit) as $scheduledPublish) {
            $events[] = new ComingUpEvent(ComingUpKind::Publishes, $scheduledPublish->getPublishAt(), $scheduledPublish->getEntry());
        }

        foreach ($this->entries->findStartingAfter($now, $this->limit) as $entry) {
            $from = $entry->getDateWindow()->from;
            if (null !== $from) {
                $events[] = new ComingUpEvent(ComingUpKind::Starts, $from, $entry);
            }
        }

        foreach ($this->entries->findEndingAfter($now, $this->limit) as $entry) {
            $until = $entry->getDateWindow()->until;
            if (null !== $until) {
                $events[] = new ComingUpEvent(ComingUpKind::Ends, $until, $entry);
            }
        }

        $order = array_flip(array_map(static fn (ComingUpKind $kind): string => $kind->value, ComingUpKind::cases()));
        usort($events, static fn (ComingUpEvent $a, ComingUpEvent $b): int => [$a->at, $order[$a->kind->value]] <=> [$b->at, $order[$b->kind->value]]);

        return array_slice($events, 0, $this->limit);
    }
}
