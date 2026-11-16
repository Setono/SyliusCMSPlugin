<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem;

use Doctrine\ORM\Event\OnFlushEventArgs;
use Setono\SyliusCMSPlugin\Model\DraftInterface;
use Setono\SyliusCMSPlugin\Model\EntryAudienceGroupInterface;
use Setono\SyliusCMSPlugin\Model\EntryChannelInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\EntryTranslationInterface;
use Setono\SyliusCMSPlugin\Model\ScheduledPublishInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Has the problems of the entries a flush writes counted once the flush is done, in the same request and transaction:
 * those whose rows, translations, channels, audience, draft or scheduled publish it inserts, updates or deletes, such
 * as an autosave, a publish, a discard, the on-save settings or a restore. Each is counted once, and an entry the flush
 * deletes isn't. Other writes, such as the builder's presence, count nothing
 */
final class ProblemCountListener implements ResetInterface
{
    /** @var array<int, EntryInterface> by object id */
    private array $written = [];

    /** @var array<int, true> by object id */
    private array $deleted = [];

    public function __construct(private readonly ProblemCountUpdaterInterface $updater)
    {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $unitOfWork = $args->getObjectManager()->getUnitOfWork();

        foreach ([...$unitOfWork->getScheduledEntityInsertions(), ...$unitOfWork->getScheduledEntityUpdates()] as $entity) {
            $this->write($entity);
        }

        foreach ($unitOfWork->getScheduledEntityDeletions() as $entity) {
            if ($entity instanceof EntryInterface) {
                $this->deleted[spl_object_id($entity)] = true;

                continue;
            }

            $this->write($entity);
        }
    }

    public function postFlush(): void
    {
        $entries = array_diff_key($this->written, $this->deleted);
        $this->reset();

        if ([] !== $entries) {
            $this->updater->update(array_values($entries));
        }
    }

    public function reset(): void
    {
        $this->written = $this->deleted = [];
    }

    private function write(object $entity): void
    {
        $entry = self::entryOf($entity);
        if (null !== $entry) {
            $this->written[spl_object_id($entry)] = $entry;
        }
    }

    private static function entryOf(object $entity): ?EntryInterface
    {
        return match (true) {
            $entity instanceof EntryInterface => $entity,
            $entity instanceof EntryTranslationInterface => self::translatableOf($entity),
            $entity instanceof DraftInterface, $entity instanceof EntryChannelInterface, $entity instanceof EntryAudienceGroupInterface, $entity instanceof ScheduledPublishInterface => $entity->getEntry(),
            default => null,
        };
    }

    private static function translatableOf(EntryTranslationInterface $translation): ?EntryInterface
    {
        $translatable = $translation->getTranslatable();

        return $translatable instanceof EntryInterface ? $translatable : null;
    }
}
