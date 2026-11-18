<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

use Psr\Clock\ClockInterface;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\Change;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\ChangeSet;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\ChangeSetApplier;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\ChangeSetMerger;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\Merge;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetEntryValue;
use Setono\SyliusCMSPlugin\Context\ContextCheck;
use Setono\SyliusCMSPlugin\Context\ContextCheckInterface;
use Setono\SyliusCMSPlugin\EntryFormat\EntryDenormalizer;
use Setono\SyliusCMSPlugin\Localization\ChannelLocales;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Repository\SavedChangeSetRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\ScheduledPublishRepositoryInterface;
use Setono\SyliusCMSPlugin\Validation\EntryUrlValidator;
use Setono\SyliusCMSPlugin\Validation\EntryUrlValidatorInterface;
use Setono\SyliusCMSPlugin\Validation\ValidationError;

/**
 * A save holds back a slug whose URL another route takes, the one exception to a draft saving while invalid: the rest
 * saves, the slug keeps its last saved value, and the save says why (docs/spec/urls-and-seo.md#reserved-urls). A
 * snippet's declared context is held back the same way while something depends on it, which the save lists, held
 * against the working copy with the rest of the save in it (docs/spec/slots.md#snippets-and-their-context).
 * Saves are kept for a day for merging onto. Each one runs with the entry's row locked (EntryLock), so saves of one entry
 * wait for each other, and loading waits for a save in flight, so a lock token always matches the working copy it came with.
 * A save that changes something, a discard and a replace record on the entry who made it and when. While a scheduled
 * publish waits, the draft is locked, so saves, discards and replaces are refused
 */
final readonly class Autosave implements AutosaveInterface
{
    /**
     * How long saves are kept for merging onto. A builder that hasn't saved or pulled colleagues' changes in for longer
     * has to load the entry again
     */
    public const KEPT_FOR = '1 day';

    public function __construct(
        private EntryLock $entryLock,
        private WorkingCopyManagerInterface $workingCopies,
        private SavedChangeSetRepositoryInterface $savedChangeSets,
        private ChangeSetMerger $merger,
        private ChangeSetApplier $applier,
        private ClockInterface $clock,
        private EditRecorder $editRecorder,
        private EntryDenormalizer $entryDenormalizer,
        private EntryUrlValidatorInterface $urlValidator,
        private ScheduledPublishRepositoryInterface $scheduledPublishes,
        private ChannelLocales $channelLocales,
        private ContextCheckInterface $contextCheck,
    ) {
    }

    public function load(EntryInterface $entry): LoadedWorkingCopy
    {
        return $this->entryLock->read($entry, fn (): LoadedWorkingCopy => new LoadedWorkingCopy(
            $this->workingCopies->getWorkingCopy($entry),
            $this->savedChangeSets->findLatestLockToken($entry),
        ));
    }

    public function save(EntryInterface $entry, ChangeSet $changeSet, int $lockToken): SaveResult
    {
        return $this->entryLock->write($entry, function () use ($entry, $changeSet, $lockToken): SaveResult {
            $this->assertUnlocked($entry);

            $latest = $this->savedChangeSets->findLatestLockToken($entry);
            $theirs = $this->savedChangeSets->findChangeSetsSince($entry, $lockToken);
            if ($lockToken > $latest || count($theirs) !== $latest - $lockToken) {
                throw new StaleLockTokenException(sprintf('The lock token %d is not one the working copy at lock token %d can merge onto', $lockToken, $latest));
            }

            $workingCopy = $this->workingCopies->getWorkingCopy($entry);
            $merge = [] === $theirs ? new Merge($changeSet, []) : $this->merger->merge(
                new ChangeSet(array_merge(...array_map(static fn (ChangeSet $saved): array => $saved->changes, $theirs))),
                $changeSet,
                $workingCopy->getBlockTree(),
            );

            $refused = $this->refusedSlugs($workingCopy, $merge->changeSet);
            $changes = [] === $refused ? $merge->changeSet : new ChangeSet(array_values(array_filter(
                $merge->changeSet->changes,
                static fn (Change $change): bool => !isset($refused[self::slug($change)[0] ?? '']),
            )));

            $refusedContext = [];
            if ([] !== $changes->changes) {
                $declared = $workingCopy instanceof SnippetInterface ? $workingCopy->getContext() : null;
                $this->applier->apply($changes, $workingCopy, $this->channelLocales->ofChannels($workingCopy->getChannelCodes()));

                $refusedContext = $this->refusedContext($workingCopy, $declared);
                if ([] !== $refusedContext) {
                    $changes = new ChangeSet(array_values(array_filter($changes->changes, static fn (Change $change): bool => !self::isContext($change))));
                }
            }

            if ([] !== $changes->changes) {
                $this->number($entry, ++$latest, $changes);
                $this->editRecorder->record($entry);
                $this->workingCopies->save($entry, $workingCopy);
            }

            return new SaveResult(new LoadedWorkingCopy($workingCopy, $latest), [] !== $theirs, $merge->clashes, [...array_values($refused), ...$refusedContext], [] !== $refusedContext);
        });
    }

    public function discard(EntryInterface $entry): LoadedWorkingCopy
    {
        return $this->entryLock->write($entry, function () use ($entry): LoadedWorkingCopy {
            $this->assertUnlocked($entry);

            $latest = $this->savedChangeSets->findLatestLockToken($entry);

            if ($this->workingCopies->hasDraft($entry)) {
                $this->number($entry, ++$latest, new ChangeSet([]));
                $this->editRecorder->record($entry);
                $this->workingCopies->discard($entry);
            }

            return new LoadedWorkingCopy($this->workingCopies->getWorkingCopy($entry), $latest);
        });
    }

    public function replace(EntryInterface $entry, array $content): LoadedWorkingCopy
    {
        return $this->entryLock->write($entry, function () use ($entry, $content): LoadedWorkingCopy {
            $this->assertUnlocked($entry);

            $workingCopy = $this->workingCopies->getWorkingCopy($entry);
            $this->entryDenormalizer->denormalize($content, $workingCopy);

            $lockToken = $this->savedChangeSets->findLatestLockToken($entry) + 1;
            $this->number($entry, $lockToken, new ChangeSet([]));
            $this->editRecorder->record($entry);
            $this->workingCopies->save($entry, $workingCopy);

            return new LoadedWorkingCopy($workingCopy, $lockToken);
        });
    }

    /**
     * @throws DraftLockedException while a scheduled publish waits. One that failed no longer does
     */
    private function assertUnlocked(EntryInterface $entry): void
    {
        $scheduledPublish = $this->scheduledPublishes->findOneByEntry($entry);
        if (null !== $scheduledPublish && !$scheduledPublish->isFailed()) {
            throw new DraftLockedException($scheduledPublish);
        }
    }

    /**
     * What's in the way of the change the save made to a snippet's declared context, which then keeps its last saved
     * value
     *
     * @param list<string>|null $declared what the snippet declared before the save, or null for an entry that isn't one
     *
     * @return list<ValidationError>
     */
    private function refusedContext(EntryInterface $workingCopy, ?array $declared): array
    {
        if (null === $declared || !$workingCopy instanceof SnippetInterface || $workingCopy->getContext() === $declared) {
            return [];
        }

        $blockers = $this->contextCheck->blockers($workingCopy, $declared, $workingCopy->getContext());
        if ([] !== $blockers) {
            $workingCopy->setContext($declared);
        }

        return $blockers;
    }

    private static function isContext(Change $change): bool
    {
        return $change instanceof SetEntryValue && ContextCheck::FIELD === $change->field && null === $change->locale;
    }

    /**
     * The slugs the changes end with whose URL another route takes, by locale
     *
     * @return array<string, ValidationError>
     */
    private function refusedSlugs(EntryInterface $workingCopy, ChangeSet $changeSet): array
    {
        $slugs = [];
        foreach ($changeSet->changes as $change) {
            $slug = self::slug($change);
            if (null !== $slug) {
                $slugs[$slug[0]] = $slug[1];
            }
        }

        $slugs = array_filter($slugs, static fn (string $slug): bool => '' !== $slug);

        return [] === $slugs ? [] : $this->urlValidator->refuseUrlsTakenByRoutes($workingCopy, $slugs);
    }

    /**
     * The locale and the slug of a change of a slug, '' for one emptied, or null for another change. A slug without a
     * locale is left for the change set to refuse
     *
     * @return array{string, string}|null
     */
    private static function slug(Change $change): ?array
    {
        if (!$change instanceof SetEntryValue || EntryUrlValidator::FIELD !== $change->field || null === $change->locale) {
            return null;
        }

        return [$change->locale, is_string($change->value) ? $change->value : ''];
    }

    /**
     * Records a save under the lock token the working copy has after it, and forgets the saves too old to
     * merge onto. It's written on the next flush
     */
    private function number(EntryInterface $entry, int $lockToken, ChangeSet $changeSet): void
    {
        $now = $this->clock->now();
        $this->savedChangeSets->removeSavedBefore($entry, $now->modify('-' . self::KEPT_FOR));
        $this->savedChangeSets->record($entry, $lockToken, $changeSet, $now);
    }
}
