<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem;

use Doctrine\ORM\Event\OnFlushEventArgs;
use Setono\SyliusCMSPlugin\Model\DraftInterface;
use Setono\SyliusCMSPlugin\Model\EntryAudienceGroupInterface;
use Setono\SyliusCMSPlugin\Model\EntryChannelInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\EntryTranslationInterface;

/**
 * Drops the problem summary once a flush that writes an entry, a translation, its channels or audience, or a draft is
 * done, so the lists and the Content badge count again with what was saved, such as a problem fixed in the builder.
 * Other writes, such as the builder's presence, leave it
 */
final class ProblemSummaryListener
{
    private bool $written = false;

    public function __construct(private readonly ProblemSummaryProviderInterface $summaries)
    {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $unitOfWork = $args->getObjectManager()->getUnitOfWork();
        $scheduled = [...$unitOfWork->getScheduledEntityInsertions(), ...$unitOfWork->getScheduledEntityUpdates(), ...$unitOfWork->getScheduledEntityDeletions()];

        if ([] !== array_filter($scheduled, self::isOfAnEntry(...))) {
            $this->written = true;
        }
    }

    public function postFlush(): void
    {
        if ($this->written) {
            $this->written = false;
            $this->summaries->clear();
        }
    }

    private static function isOfAnEntry(object $entity): bool
    {
        return $entity instanceof EntryInterface || $entity instanceof EntryTranslationInterface || $entity instanceof DraftInterface
            || $entity instanceof EntryChannelInterface || $entity instanceof EntryAudienceGroupInterface;
    }
}
