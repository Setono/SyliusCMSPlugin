<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Who has an entry open in the builder. The builder says so every few seconds, and someone it stops hearing from is
 * dropped. See "Saving" in docs/spec/publishing.md and "Top bar" in docs/spec/builder.md
 */
interface PresenceTrackerInterface
{
    /**
     * Records that the admin user has the entry open now
     */
    public function see(EntryInterface $entry, UserInterface $editor): void;

    /**
     * The names of the other admin users who have the entry open, by name
     *
     * @return list<string>
     */
    public function others(EntryInterface $entry, UserInterface $editor): array;
}
