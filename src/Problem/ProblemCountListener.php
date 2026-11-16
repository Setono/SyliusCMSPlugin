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
 * as an autosave, a publish, a discard, the on-save settings or a restore, and those that reference what it writes of
 * what a ReferenceProblemKindInterface kind reads, such as a deleted customer group. Each is counted once, and an
 * entry the flush deletes isn't. Other writes, such as the builder's presence, count nothing
 */
final class ProblemCountListener implements ResetInterface
{
    /** @var array<int, EntryInterface> by object id */
    private array $written = [];

    /** @var array<int, object> what the flush wrote of the referenced classes, by object id */
    private array $referenced = [];

    /**
     * @param iterable<ReferenceProblemKindInterface> $referenceKinds the kinds about what entries reference, which
     *                                                               RegisterProblemKindsPass hands it
     * @param list<class-string> $referencedClasses what they reference, which RegisterProblemKindsPass reads from them
     */
    public function __construct(
        private readonly ProblemCountUpdaterInterface $updater,
        private readonly iterable $referenceKinds = [],
        private readonly array $referencedClasses = [],
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $unitOfWork = $args->getObjectManager()->getUnitOfWork();
        $deletions = $unitOfWork->getScheduledEntityDeletions();

        foreach ([...$unitOfWork->getScheduledEntityInsertions(), ...$unitOfWork->getScheduledEntityUpdates(), ...$deletions] as $entity) {
            $this->write($entity);
        }

        // An entry the flush deletes has nothing to count
        foreach ($deletions as $entity) {
            unset($this->written[spl_object_id($entity)]);
        }
    }

    public function postFlush(): void
    {
        $entries = $this->written;
        $referenced = $this->referenced;
        $this->reset();

        // The kinds are built only when something they reference was written
        if ([] !== $referenced) {
            foreach ($this->entriesReferencing($referenced) as $entry) {
                $entries[spl_object_id($entry)] = $entry;
            }
        }

        if ([] !== $entries) {
            $this->updater->update(array_values($entries));
        }
    }

    public function reset(): void
    {
        $this->written = $this->referenced = [];
    }

    private function write(object $entity): void
    {
        $entry = self::entryOf($entity);
        if (null !== $entry) {
            $this->written[spl_object_id($entry)] = $entry;
        }

        if (self::isAny($entity, $this->referencedClasses)) {
            $this->referenced[spl_object_id($entity)] = $entity;
        }
    }

    /**
     * The entries that the kinds about what was written find
     *
     * @param array<int, object> $referenced
     *
     * @return \Generator<EntryInterface>
     */
    private function entriesReferencing(array $referenced): \Generator
    {
        foreach ($this->referenceKinds as $kind) {
            $written = array_values(array_filter($referenced, static fn (object $object): bool => self::isAny($object, $kind::getReferencedClasses())));
            if ([] !== $written) {
                yield from $kind->findEntriesReferencing($written);
            }
        }
    }

    /**
     * @param list<class-string> $classes
     */
    private static function isAny(object $object, array $classes): bool
    {
        foreach ($classes as $class) {
            if ($object instanceof $class) {
                return true;
            }
        }

        return false;
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
