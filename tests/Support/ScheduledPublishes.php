<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\ScheduledPublishInterface;
use Setono\SyliusCMSPlugin\Repository\ScheduledPublishRepositoryInterface;

/**
 * Helpers for functional and UI tests of scheduled publishes, whose delayed messages SentMessages reads and consumes
 */
trait ScheduledPublishes
{
    use SentMessages;

    /**
     * The entry's scheduled publish as the database holds it, or null when it has none
     */
    protected static function scheduledPublishOf(EntryInterface $entry): ?ScheduledPublishInterface
    {
        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.scheduled_publish');
        $manager->clear();

        /** @var ScheduledPublishRepositoryInterface $scheduledPublishes */
        $scheduledPublishes = self::getContainer()->get('setono_sylius_cms.repository.scheduled_publish');
        $reloaded = $manager->find($entry::class, $entry->getId());
        self::assertInstanceOf(EntryInterface::class, $reloaded);

        return $scheduledPublishes->findOneByEntry($reloaded);
    }
}
