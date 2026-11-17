<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem;

use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
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
 * what a ReferenceProblemKindInterface kind reads, such as a deleted customer group. Of a record that entries refer to
 * by a field, such as a product by its code, only deleting it or changing that field counts, and then also the entries
 * that refer to the field's former value, so writing products and taxons otherwise runs no query. An update is read in
 * preUpdate, which has its change set. Each entry is counted once, and an entry the flush deletes isn't. Other writes,
 * such as the builder's presence, count nothing
 */
final class ProblemCountListener implements ResetInterface
{
    /** @var array<int, EntryInterface> by object id */
    private array $written = [];

    /**
     * @var array<int, array{record: object, deleted: bool, changes: array<array-key, mixed>}> what the flush wrote of
     *                                                                                         the referenced classes
     *                                                                                         that may change what
     *                                                                                         entries reference, by
     *                                                                                         object id, with an
     *                                                                                         update's change set
     */
    private array $referenced = [];

    /**
     * @param iterable<ReferenceProblemKindInterface> $referenceKinds the kinds about what entries reference, which
     *                                                               RegisterProblemKindsPass hands it
     * @param list<class-string> $referencedClasses what they reference, which RegisterProblemKindsPass reads from them
     * @param array<class-string, list<string>> $referenceFields the referenced classes that every kind about them
     *                                                           refers to by a field, with those fields, which
     *                                                           RegisterProblemKindsPass reads from them: of these,
     *                                                           only a delete or a change of one of the fields counts
     */
    public function __construct(
        private readonly ProblemCountUpdaterInterface $updater,
        private readonly iterable $referenceKinds = [],
        private readonly array $referencedClasses = [],
        private readonly array $referenceFields = [],
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $unitOfWork = $args->getObjectManager()->getUnitOfWork();
        $insertions = $unitOfWork->getScheduledEntityInsertions();
        $deletions = $unitOfWork->getScheduledEntityDeletions();

        foreach ([...$insertions, ...$unitOfWork->getScheduledEntityUpdates(), ...$deletions] as $entity) {
            $entry = self::entryOf($entity);
            if (null !== $entry) {
                $this->written[spl_object_id($entry)] = $entry;
            }
        }

        // The updates of what's referenced come with their change sets in preUpdate
        foreach ($insertions as $entity) {
            $this->writeReferenced(['record' => $entity, 'deleted' => false, 'changes' => []]);
        }

        foreach ($deletions as $entity) {
            $this->writeReferenced(['record' => $entity, 'deleted' => true, 'changes' => []]);

            // An entry the flush deletes has nothing to count
            unset($this->written[spl_object_id($entity)]);
        }
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $this->writeReferenced(['record' => $args->getObject(), 'deleted' => false, 'changes' => $args->getEntityChangeSet()]);
    }

    public function postFlush(): void
    {
        $entries = $this->written;
        $referenced = $this->referenced;
        $this->reset();

        // The kinds are built only when something they reference was written in a way that may change what's referenced
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

    /**
     * @param array{record: object, deleted: bool, changes: array<array-key, mixed>} $write
     */
    private function writeReferenced(array $write): void
    {
        if (null !== self::referenceFieldsOf($write, $this->referencedClasses, $this->referenceFields)) {
            $this->referenced[spl_object_id($write['record'])] = $write;
        }
    }

    /**
     * The entries that the kinds about what was written find, each kind asked only about the writes that may change
     * what it finds, with the former values of the fields it refers to them by
     *
     * @param array<int, array{record: object, deleted: bool, changes: array<array-key, mixed>}> $referenced
     *
     * @return \Generator<EntryInterface>
     */
    private function entriesReferencing(array $referenced): \Generator
    {
        foreach ($this->referenceKinds as $kind) {
            $classes = $kind::getReferencedClasses();
            $fields = array_map(static fn (string $field): array => [$field], $kind::getReferenceFields());

            $written = $formerValues = [];
            foreach ($referenced as $write) {
                $referenceFields = self::referenceFieldsOf($write, $classes, $fields);
                if (null === $referenceFields) {
                    continue;
                }

                $written[] = $write['record'];
                foreach ($referenceFields as $field) {
                    $change = $write['changes'][$field] ?? null;
                    $formerValue = is_array($change) ? ($change[0] ?? null) : null;
                    if (is_string($formerValue) || is_int($formerValue)) {
                        $formerValues[] = (string) $formerValue;
                    }
                }
            }

            if ([] !== $written) {
                yield from $kind->findEntriesReferencing($written, array_values(array_unique($formerValues)));
            }
        }
    }

    /**
     * Whether the write of a record of one of the classes may change which entries refer to it: deleting it, any write
     * of a class not referred to by a field, or else changing one of the fields it's referred to by. It gives null when
     * it can't, and otherwise the fields it's referred to by
     *
     * @param array{record: object, deleted: bool, changes: array<array-key, mixed>} $write
     * @param list<class-string> $classes
     * @param array<class-string, list<string>> $fields the fields some of the classes are referred to by
     *
     * @return list<string>|null
     */
    private static function referenceFieldsOf(array $write, array $classes, array $fields): ?array
    {
        $mayChange = false;
        $referenceFields = [];
        foreach ($classes as $class) {
            if (!$write['record'] instanceof $class) {
                continue;
            }

            $byFields = $fields[$class] ?? null;
            if (null === $byFields) {
                $mayChange = true;

                continue;
            }

            $referenceFields = [...$referenceFields, ...$byFields];
            if ($write['deleted'] || [] !== array_intersect($byFields, array_map(strval(...), array_keys($write['changes'])))) {
                $mayChange = true;
            }
        }

        return $mayChange ? $referenceFields : null;
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
