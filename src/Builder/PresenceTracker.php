<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder;

use Psr\Clock\ClockInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Repository\EntryPresenceRepositoryInterface;
use Setono\SyliusCMSPlugin\Security\EditorName;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class PresenceTracker implements PresenceTrackerInterface
{
    /**
     * How long someone counts as having the entry open after their builder last said so. The builder says it every
     * five seconds, so this allows for a few that are late or lost
     */
    public const TIMEOUT = '20 seconds';

    public function __construct(
        private EntryPresenceRepositoryInterface $presences,
        private ClockInterface $clock,
    ) {
    }

    public function see(EntryInterface $entry, UserInterface $editor): void
    {
        $now = $this->clock->now();

        $this->presences->removeSeenBefore($entry, $this->expiry($now));
        $this->presences->see($entry, $editor->getUserIdentifier(), EditorName::of($editor), $now);
    }

    public function others(EntryInterface $entry, UserInterface $editor): array
    {
        return $this->presences->findNamesSeenSince($entry, $this->expiry($this->clock->now()), $editor->getUserIdentifier());
    }

    private function expiry(\DateTimeImmutable $now): \DateTimeImmutable
    {
        return $now->modify('-' . self::TIMEOUT);
    }
}
