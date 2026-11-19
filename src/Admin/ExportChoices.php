<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Setono\SyliusCMSPlugin\Context\EntryTitle;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\MediaFolderInterface;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Preset\EditorPresetContent;
use Setono\SyliusCMSPlugin\Preset\InvalidPresetException;
use Setono\SyliusCMSPlugin\Preset\TranslationKey;
use Setono\SyliusCMSPlugin\Registry\ContentTypeMetadata;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Repository\EditorPresetRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\MediaFolderRepositoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * What the Transfer screen's export offers to pick, and how it names what's picked and what comes along: entries,
 * found by their title or slug, editor presets, and media folders under their parents. See "Transfer" in
 * docs/spec/admin.md
 */
final readonly class ExportChoices
{
    /** The most entries a search lists */
    public const SEARCH_LIMIT = 20;

    public function __construct(
        private EntryRepositoryInterface $entryRepository,
        private EditorPresetRepositoryInterface $presetRepository,
        private MediaFolderRepositoryInterface $folderRepository,
        private ContentTypeRegistryInterface $contentTypes,
        private FallbackValues $fallbackValues,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * The entries with these UUIDs, in the order given. A UUID with none is left out
     *
     * @param list<string> $uuids
     *
     * @return list<ExportChoice>
     */
    public function entries(array $uuids, string $localeCode): array
    {
        $entries = [];
        foreach ($this->entryRepository->findByUuids($uuids) as $entry) {
            $entries[$entry->getUuid()] = $entry;
        }

        return $this->ofEntries(array_values(array_filter(array_map(static fn (string $uuid): ?EntryInterface => $entries[$uuid] ?? null, $uuids))), $localeCode);
    }

    /**
     * The entries whose title or slug contains the search, newest first, but those of a disabled content type and those
     * left out
     *
     * @param list<string> $leftOut the UUIDs of entries not to list, such as those picked already
     *
     * @return list<ExportChoice>
     */
    public function search(string $search, array $leftOut, string $localeCode): array
    {
        if ('' === trim($search)) {
            return [];
        }

        $disabled = array_map(
            static fn (ContentTypeMetadata $contentType): string => $contentType->code,
            array_filter($this->contentTypes->all(), static fn (ContentTypeMetadata $contentType): bool => $contentType->disabled),
        );

        $found = array_filter(
            $this->entryRepository->search($search, self::SEARCH_LIMIT + count($leftOut), array_values($disabled)),
            static fn (EntryInterface $entry): bool => !in_array($entry->getUuid(), $leftOut, true),
        );

        return array_slice($this->ofEntries(array_values($found), $localeCode), 0, self::SEARCH_LIMIT);
    }

    /**
     * @param list<EntryInterface> $entries
     *
     * @return list<ExportChoice>
     */
    public function ofEntries(array $entries, string $localeCode): array
    {
        return array_map(fn (EntryInterface $entry): ExportChoice => new ExportChoice(
            $entry->getUuid(),
            EntryTitle::inLocale($entry, $localeCode, $this->fallbackValues),
            $this->contentTypes->getByEntry($entry)->label,
        ), $entries);
    }

    /**
     * @param list<MediaItemInterface> $mediaItems
     *
     * @return list<ExportChoice>
     */
    public function ofMediaItems(array $mediaItems): array
    {
        return array_map(static fn (MediaItemInterface $mediaItem): ExportChoice => new ExportChoice(
            $mediaItem->getUuid(),
            $mediaItem->getName(),
            $mediaItem->getOriginalFilename(),
        ), $mediaItems);
    }

    /**
     * Every editor preset, the one saved first first. One that can't be read is named by its UUID
     *
     * @return list<ExportChoice>
     */
    public function presets(): array
    {
        $content = new EditorPresetContent();
        $presets = [];
        foreach ($this->presetRepository->findAllInCreationOrder() as $row) {
            try {
                $preset = $content->read($row);
                $presets[] = new ExportChoice($row->getUuid(), (string) $this->text($preset->name), $this->text($preset->category));
            } catch (InvalidPresetException) {
                $presets[] = new ExportChoice($row->getUuid(), $row->getUuid());
            }
        }

        return $presets;
    }

    /**
     * Every media folder, each under its parent, in name order as the library lists them
     *
     * @return list<ExportFolderChoice>
     */
    public function folders(): array
    {
        $children = [];
        foreach ($this->folderRepository->findAllByName() as $folder) {
            $children[$folder->getParent()?->getUuid() ?? ''][] = $folder;
        }

        return self::below('', 0, $children);
    }

    /**
     * @param array<string, list<MediaFolderInterface>> $children by their parent's UUID, '' for the top, in name order
     *
     * @return list<ExportFolderChoice>
     */
    private static function below(string $parent, int $depth, array $children): array
    {
        $folders = [];
        foreach ($children[$parent] ?? [] as $folder) {
            $folders[] = new ExportFolderChoice($folder->getUuid(), $folder->getName(), $depth);
            array_push($folders, ...self::below($folder->getUuid(), $depth + 1, $children));
        }

        return $folders;
    }

    private function text(string|TranslationKey|null $text): ?string
    {
        return $text instanceof TranslationKey ? $this->translator->trans($text->key, [], $text->domain) : $text;
    }
}
