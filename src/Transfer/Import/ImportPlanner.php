<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

use Setono\SyliusCMSPlugin\Context\EntryTitle;
use Setono\SyliusCMSPlugin\Field\ReferenceType;
use Setono\SyliusCMSPlugin\Localization\Availability;
use Setono\SyliusCMSPlugin\Media\RefusedFileException;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Repository\EditorPresetRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\HomepageRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\MediaFolderRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\MediaItemRepositoryInterface;
use Setono\SyliusCMSPlugin\Routing\EntryPath;
use Setono\SyliusCMSPlugin\Routing\Slug;
use Setono\SyliusCMSPlugin\Routing\UrlClashFinderInterface;
use Setono\SyliusCMSPlugin\Transfer\Format\ExportFormat;
use Setono\SyliusCMSPlugin\Tree\ReferenceFinder;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgraderInterface;
use Setono\SyliusCMSPlugin\Validation\EmbedCodeValidator;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * Plans an import from what the target holds, matched by UUID only: what it creates, updates, leaves unchanged or
 * alone (RecordDecision), the conflicts, the Embed code blocks it can't bring in while embed_code is off, the homepage
 * changes and the references that don't resolve. Entries are planned in the order they're written: the snippets an
 * entry shows before it, so they're published before it. See "What an import does", "Conflicts" and "Code the target
 * must have" in docs/spec/transfer.md. Media items are planned as entries and presets are, and the folders the ones it
 * writes sit in are created when the target lacks them
 */
final readonly class ImportPlanner implements ImportPlannerInterface
{
    public function __construct(
        private EntryRepositoryInterface $entries,
        private EditorPresetRepositoryInterface $presets,
        private ContentTypeRegistryInterface $contentTypes,
        private TargetRecords $targetRecords,
        private UrlClashFinderInterface $urlClashes,
        private Availability $availability,
        private EmbedCodeValidator $embedCodeValidator,
        private StoredDataUpgraderInterface $upgrader,
        private HomepageRepositoryInterface $homepages,
        private UnresolvedReferences $unresolvedReferences,
        private ReferenceFinder $referenceFinder,
        private MediaItemRepositoryInterface $mediaItemRepository,
        private MediaFolderRepositoryInterface $mediaFolderRepository,
    ) {
    }

    public function plan(ReadExport $export): ImportPlan
    {
        $refusals = $export->refusals;
        $conflicts = $entries = $presets = [];

        /** @var array<string, array{ReadEntry, EntryInterface|null}> $written the entries it writes, with what the target holds of each */
        $written = [];
        foreach ($this->inWriteOrder($export->entries) as $entry) {
            $target = $this->entries->findOneByUuid($entry->uuid());
            if (null !== $target && $this->contentTypes->getByEntry($target)->code !== $entry->contentType->code) {
                $refusals[] = new ImportRefusal($entry->uuid(), new TranslatableMessage('setono_sylius_cms.import.other_content_type', [
                    '%title%' => $entry->label(),
                    '%content_type%' => $this->contentTypes->getByEntry($target)->code,
                ]));

                continue;
            }

            $decision = RecordDecision::of($entry->picked, $entry->exported->changedAt, null === $target ? null : $this->targetRecords->ofEntry($entry, $target));
            $entries[] = new PlannedRecord(RecordKind::Entry, $entry->uuid(), $entry->label(), $entry->picked, $decision->action, $entry->contentType->code);
            foreach ($decision->conflicts as $kind) {
                $conflicts[] = new ImportConflict($kind, $entry->uuid(), self::conflictMessage($kind, $entry->label(), $entry->exported->changedAt, $target?->getChangedAt()));
            }

            if ($decision->writes()) {
                $written[$entry->uuid()] = [$entry, $target];
            }
        }

        $writtenPresets = [];
        foreach ($export->presets as $preset) {
            $target = $this->presets->findOneByUuid($preset->uuid());
            $decision = RecordDecision::of($preset->picked, $preset->changedAt(), null === $target ? null : $this->targetRecords->ofPreset($preset, $target));
            $presets[] = new PlannedRecord(RecordKind::Preset, $preset->uuid(), $preset->label(), $preset->picked, $decision->action);
            foreach ($decision->conflicts as $kind) {
                $conflicts[] = new ImportConflict($kind, $preset->uuid(), self::conflictMessage($kind, $preset->label(), $preset->changedAt(), $target?->getChangedAt()));
            }

            if ($decision->writes()) {
                $writtenPresets[] = $preset;
            }
        }

        foreach ($written as [$entry, $target]) {
            array_push($conflicts, ...$this->urlClashes($entry, $written));
            if (!$this->bringsInEmbedCode($entry, $target)) {
                $refusals[] = new ImportRefusal($entry->uuid(), new TranslatableMessage('setono_sylius_cms.import.embed_code', ['%title%' => $entry->label()]));
            }
        }

        $mediaItems = $writtenMediaItems = [];
        foreach ($export->mediaItems as $mediaItem) {
            $target = $this->mediaItemRepository->findOneByUuid($mediaItem->uuid());
            if (null !== $target && $target->getKind() !== $mediaItem->file->kind) {
                // A file replaces only a file of its kind, as in the library
                $refusals[] = new ImportRefusal($mediaItem->uuid(), new TranslatableMessage('setono_sylius_cms.import.media_refused', [
                    '%name%' => $mediaItem->label(),
                    '%reason%' => RefusedFileException::otherKind($mediaItem->exported->filename, $target->getKind())->reason,
                ]));

                continue;
            }

            $changedAt = $mediaItem->exported->changedAt;
            $decision = RecordDecision::of($mediaItem->picked, $changedAt, null === $target ? null : new TargetRecord($target->getChangedAt(), $mediaItem->isIdenticalTo($target)));
            $mediaItems[] = new PlannedRecord(RecordKind::MediaItem, $mediaItem->uuid(), $mediaItem->label(), $mediaItem->picked, $decision->action);
            foreach ($decision->conflicts as $kind) {
                $conflicts[] = new ImportConflict($kind, $mediaItem->uuid(), self::conflictMessage($kind, $mediaItem->label(), $changedAt, $target?->getChangedAt()));
            }

            if ($decision->writes()) {
                $writtenMediaItems[] = $mediaItem;
            }
        }

        return new ImportPlan(
            $entries,
            $presets,
            $conflicts,
            $refusals,
            $this->homepageChanges($written),
            $this->unresolvedReferences->in($export, array_values(array_map(static fn (array $written): ReadEntry => $written[0], $written)), $writtenPresets, $writtenMediaItems),
            $mediaItems,
            $this->mediaFolders($export, $writtenMediaItems),
        );
    }

    /**
     * The folders the media items it writes sit in, with their parents. A folder the target lacks is created, and one
     * it has is neither moved nor renamed: unchanged when it has the export's name and parent, and otherwise left alone
     *
     * @param list<ReadMediaItem> $mediaItems
     *
     * @return list<PlannedRecord>
     */
    private function mediaFolders(ReadExport $export, array $mediaItems): array
    {
        $folders = [];
        foreach ($export->foldersOf(array_map(static fn (ReadMediaItem $mediaItem): string => $mediaItem->uuid(), $mediaItems)) as $folder) {
            $target = $this->mediaFolderRepository->findOneByUuid($folder->uuid);
            $action = match (true) {
                null === $target => ImportAction::Create,
                $target->getName() === $folder->name && $target->getParent()?->getUuid() === $folder->parent => ImportAction::Unchanged,
                default => ImportAction::LeaveAlone,
            };

            $folders[] = new PlannedRecord(RecordKind::MediaFolder, $folder->uuid, $folder->name, false, $action);
        }

        return $folders;
    }

    /**
     * The entries with the snippets each shows before it, and otherwise in the export's order
     *
     * @param array<string, ReadEntry> $entries
     *
     * @return list<ReadEntry>
     */
    private function inWriteOrder(array $entries): array
    {
        $ordered = $visiting = [];
        $visit = function (ReadEntry $entry) use (&$visit, &$ordered, &$visiting, $entries): void {
            if (isset($ordered[$entry->uuid()]) || isset($visiting[$entry->uuid()])) {
                return;
            }

            $visiting[$entry->uuid()] = true;
            foreach (ReferenceFinder::references($this->referenceFinder->inEntry($entry->content, $entry->contentType)) as $reference) {
                if (ReferenceType::ENTRY === $reference->type && $reference->dependency && isset($entries[$reference->id])) {
                    $visit($entries[$reference->id]);
                }
            }

            $ordered[$entry->uuid()] = $entry;
        };

        foreach ($entries as $entry) {
            $visit($entry);
        }

        return array_values($ordered);
    }

    /**
     * The entry's URLs, from its imported slugs and channels, that another entry has: one published in the target that
     * the import doesn't write, or another entry the import writes
     *
     * @param array<string, array{ReadEntry, EntryInterface|null}> $written
     *
     * @return list<ImportConflict>
     */
    private function urlClashes(ReadEntry $entry, array $written): array
    {
        if (!$entry->contentType->hasOwnUrl()) {
            return [];
        }

        $content = $entry->content;
        $slugs = Slug::of($content, fn (string $localeCode): bool => $this->availability->hasTranslation($content, $localeCode));
        $channels = $entry->exported->settings->channels;

        $conflicts = [];
        foreach ($this->urlClashes->findPublished($content, $slugs, $channels) as $clash) {
            if (!isset($written[$clash->other->uuid])) {
                $conflicts[] = self::urlClash($entry, $clash->localeCode, $clash->path, $clash->other->title ?? $clash->other->uuid, $clash->channelCodes);
            }
        }

        // Each pair of entries the import writes is checked once, from the one written later
        foreach ($written as $uuid => [$other]) {
            if ($uuid === $entry->uuid()) {
                break;
            }

            if (!$other->contentType->hasOwnUrl()) {
                continue;
            }

            $shared = array_values(array_intersect($channels, $other->exported->settings->channels));
            $otherSlugs = Slug::of($other->content, fn (string $localeCode): bool => $this->availability->hasTranslation($other->content, $localeCode));
            foreach ($slugs as $localeCode => $slug) {
                $path = EntryPath::ofSlug($entry->contentType, $slug);
                if ([] !== $shared && null !== $path && isset($otherSlugs[$localeCode]) && EntryPath::ofSlug($other->contentType, $otherSlugs[$localeCode]) === $path) {
                    $conflicts[] = self::urlClash($entry, $localeCode, $path, $other->label(), $shared);
                }
            }
        }

        return $conflicts;
    }

    /**
     * @param list<string> $channelCodes
     */
    private static function urlClash(ReadEntry $entry, string $localeCode, string $path, string $other, array $channelCodes): ImportConflict
    {
        return new ImportConflict(ConflictKind::UrlClash, $entry->uuid(), new TranslatableMessage('setono_sylius_cms.import.conflict.url_clash', [
            '%title%' => $entry->label(),
            '%url%' => sprintf('/%s/%s', $localeCode, $path),
            '%other%' => $other,
            '%channels%' => implode(', ', $channelCodes),
        ]));
    }

    /**
     * Whether the Embed code blocks of the entry's imported tree may come in: always while embed_code is on, and while
     * it's off only those in the entry's published tree in the target, with the same key and code, as publish holds to
     */
    private function bringsInEmbedCode(ReadEntry $entry, ?EntryInterface $target): bool
    {
        $published = null === $target || !$target->isPublished() ? new StoredTree(null, []) : $this->upgrader->readBlockTree($target);

        return [] === $this->embedCodeValidator->validate($entry->content->getBlockTree(), $published);
    }

    /**
     * The channels whose homepage the import changes: those an imported page becomes the homepage of, taken off the
     * page that is now, and those it stops being the homepage of
     *
     * @param array<string, array{ReadEntry, EntryInterface|null}> $written
     *
     * @return list<HomepageChange>
     */
    private function homepageChanges(array $written): array
    {
        $changes = [];
        foreach ($written as [$entry, $target]) {
            if (!$entry->content instanceof PageInterface) {
                continue;
            }

            $before = $target instanceof PageInterface ? $target->getHomepageChannelCodes() : [];
            $after = $entry->exported->settings->homepageOf;

            $current = [];
            foreach ($this->homepages->findByChannelCodes(array_values(array_diff($after, $before))) as $homepage) {
                $current[$homepage->getChannelCode()] = $homepage->getPage();
            }

            foreach (array_diff($after, $before) as $channelCode) {
                $page = $current[$channelCode] ?? null;
                $changes[] = new HomepageChange($channelCode, $page?->getUuid(), null === $page ? null : EntryTitle::of($page), $entry->uuid(), $entry->label());
            }

            foreach (array_diff($before, $after) as $channelCode) {
                $changes[] = new HomepageChange($channelCode, $entry->uuid(), $entry->label(), null, null);
            }
        }

        return $changes;
    }

    private static function conflictMessage(ConflictKind $kind, string $title, \DateTimeImmutable $exportedAt, ?\DateTimeImmutable $changedAt): TranslatableMessage
    {
        return match ($kind) {
            ConflictKind::Stale => new TranslatableMessage('setono_sylius_cms.import.conflict.stale', [
                '%title%' => $title,
                '%changed_at%' => null === $changedAt ? '' : ExportFormat::dateTime($changedAt),
                '%exported_at%' => ExportFormat::dateTime($exportedAt),
            ]),
            ConflictKind::Draft => new TranslatableMessage('setono_sylius_cms.import.conflict.draft', ['%title%' => $title]),
            ConflictKind::ScheduledPublish => new TranslatableMessage('setono_sylius_cms.import.conflict.scheduled_publish', ['%title%' => $title]),
            ConflictKind::UrlClash => throw new \LogicException('A URL clash names the URL and the other entry'),
        };
    }
}
