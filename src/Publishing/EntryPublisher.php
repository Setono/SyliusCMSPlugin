<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

use Doctrine\Persistence\ManagerRegistry;
use Psr\Clock\ClockInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * Until drafts arrive (#217), saves write straight to the rows, so publishing marks the entry published.
 * Validation (#219), drafts, revisions and events build on this seam
 */
final readonly class EntryPublisher implements EntryPublisherInterface
{
    public function __construct(
        private ManagerRegistry $managerRegistry,
        private ClockInterface $clock,
    ) {
    }

    public function publish(EntryInterface $entry): void
    {
        $entry->publish($this->clock->now()->setTimezone(new \DateTimeZone('UTC')));

        $manager = $this->managerRegistry->getManagerForClass($entry::class) ?? throw new \LogicException(sprintf('No Doctrine manager manages %s', $entry::class));
        $manager->flush();
    }
}
