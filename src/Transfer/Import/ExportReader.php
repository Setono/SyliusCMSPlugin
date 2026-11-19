<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

use Setono\SyliusCMSPlugin\EntryFormat\EntryDenormalizer;
use Setono\SyliusCMSPlugin\EntryFormat\InvalidEntryFormatException;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Preset\StartingPoint;
use Setono\SyliusCMSPlugin\Publishing\EntrySettings;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeMetadata;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistryInterface;
use Setono\SyliusCMSPlugin\Transfer\Format\ExportedEntry;
use Setono\SyliusCMSPlugin\Transfer\Format\ExportFormat;
use Setono\SyliusCMSPlugin\Transfer\Format\InvalidExportException;
use Setono\SyliusCMSPlugin\Tree\InvalidStoredTreeException;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Setono\SyliusCMSPlugin\Tree\StoredTreeSerializer;
use Setono\SyliusCMSPlugin\Upgrade\NewerVersion;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgraderInterface;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * Step 1 of an import: it reads every file of the export through Format\ExportFormat, runs the upgraders on the
 * entries and editor presets, and checks that the code knows everything they use. A content type, layout or block type
 * the code doesn't have, or one saved with a newer version than the code's, is a reason to refuse, which the plan
 * lists. A disabled one imports like any other. What isn't in the export format makes the export one it can't read.
 * See "What an import does" and "Code the target must have" in docs/spec/transfer.md
 *
 * #280 checks the zip's paths and the media files here, beside the media items' JSON this reads
 */
final readonly class ExportReader
{
    public function __construct(
        private ContentTypeRegistryInterface $contentTypes,
        private LayoutRegistryInterface $layouts,
        private BlockTypeRegistryInterface $blockTypes,
        private StoredDataUpgraderInterface $upgrader,
        private EntryDenormalizer $entryDenormalizer,
    ) {
    }

    /**
     * @throws InvalidExportException when the export has no manifest of a format this code reads, or a file isn't in
     *                                the export format
     */
    public function read(ExportSourceInterface $source): ReadExport
    {
        $format = new ExportFormat();
        $manifest = $format->decodeManifest($source->read(ExportFormat::MANIFEST));

        $refusals = $entries = [];
        $picked = array_flip($manifest->entries);
        foreach (self::uuids($source, 'entries') as $uuid) {
            $exported = $format->decodeEntry($source->read(ExportFormat::entryPath($uuid)));
            $entry = $this->readEntry($uuid, $exported, isset($picked[$uuid]), $refusals);
            if (null !== $entry) {
                $entries[$uuid] = $entry;
            }
        }

        $presets = [];
        $picked = array_flip($manifest->presets);
        foreach (self::uuids($source, 'presets') as $uuid) {
            $preset = $format->decodePreset($source->read(ExportFormat::presetPath($uuid)));
            if ($uuid !== $preset->uuid) {
                throw new InvalidExportException(sprintf('The file %s holds the preset %s', ExportFormat::presetPath($uuid), (string) $preset->uuid));
            }

            $preset = $this->readPreset($preset, $refusals);
            if (null !== $preset) {
                $presets[$uuid] = new ReadPreset($preset, isset($picked[$uuid]));
            }
        }

        $mediaItems = [];
        foreach (self::uuids($source, 'media') as $uuid) {
            $mediaItem = $format->decodeMediaItem($source->read(ExportFormat::mediaItemPath($uuid)));
            if ($uuid !== $mediaItem->uuid) {
                throw new InvalidExportException(sprintf('The file %s holds the media item %s', ExportFormat::mediaItemPath($uuid), $mediaItem->uuid));
            }

            $mediaItems[$uuid] = $mediaItem;
        }

        $mediaFolders = $source->has(ExportFormat::MEDIA_FOLDERS) ? $format->decodeMediaFolders($source->read(ExportFormat::MEDIA_FOLDERS)) : [];

        return new ReadExport($manifest, $entries, $presets, $mediaFolders, $mediaItems, $refusals);
    }

    /**
     * The entry, upgraded, with a copy of it that holds what the export holds, or null with the reasons to refuse it
     * when the code doesn't know its content type or is older than it
     *
     * @param list<ImportRefusal> $refusals
     *
     * @throws InvalidExportException
     */
    private function readEntry(string $uuid, ExportedEntry $exported, bool $picked, array &$refusals): ?ReadEntry
    {
        $data = is_array($exported->entry) ? $exported->entry : [];
        if ($uuid !== ($data['uuid'] ?? null)) {
            throw new InvalidExportException(sprintf('The file %s doesn\'t hold the entry %s', ExportFormat::entryPath($uuid), $uuid));
        }

        $title = self::titleOf($uuid, $data);
        $code = $data['contentType'] ?? null;
        if (!is_string($code) || !$this->contentTypes->has($code)) {
            $refusals[] = new ImportRefusal($uuid, new TranslatableMessage('setono_sylius_cms.import.unknown_content_type', ['%title%' => $title, '%content_type%' => is_string($code) ? $code : '']));

            return null;
        }

        $upgraded = $this->upgrader->upgradeEntry($data);
        if ([] !== $upgraded->newer) {
            array_push($refusals, ...self::newer($uuid, $title, $upgraded->newer));

            return null;
        }

        $contentType = $this->contentTypes->get($code);
        $content = $this->content($contentType, $uuid, $upgraded->data);

        $tree = $content->getBlockTree();
        if (null !== $tree->layout && !$this->layouts->has($code, $tree->layout->code)) {
            $refusals[] = new ImportRefusal($uuid, new TranslatableMessage('setono_sylius_cms.import.unknown_layout', ['%title%' => $title, '%layout%' => $tree->layout->code]));
        }

        array_push($refusals, ...$this->unknownBlockTypes($uuid, $title, $tree->allBlocks()));

        return new ReadEntry(
            new ExportedEntry($exported->changedAt, $this->settingsFor($contentType, $content, $exported->settings), $exported->placements, $upgraded->data),
            $content,
            $contentType,
            $picked,
        );
    }

    /**
     * A copy of the entry no Doctrine manager manages, holding what the export holds, through the entry format as a
     * draft is read
     *
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidExportException
     */
    private function content(ContentTypeMetadata $contentType, string $uuid, array $data): EntryInterface
    {
        $class = $contentType->modelClass;
        $content = new $class();
        if (!$content instanceof EntryInterface) {
            throw new \LogicException(sprintf('The model of the content type "%s" must be an entry', $contentType->code));
        }

        $content->setUuid($uuid);
        $content->setCreatedAt(ExportFormat::parseDateTime($data['createdAt'] ?? null, sprintf('The createdAt of the entry %s', $uuid)));

        try {
            $this->entryDenormalizer->denormalize($data, $content);
        } catch (InvalidEntryFormatException $e) {
            throw new InvalidExportException(sprintf('The entry %s isn\'t in the entry format: %s', $uuid, $e->getMessage()), previous: $e);
        }

        return $content;
    }

    /**
     * Its settings as the target takes them: only a page is a channel's homepage, and only an entry of a content type
     * ordered by position has a position
     */
    private function settingsFor(ContentTypeMetadata $contentType, EntryInterface $content, EntrySettings $settings): EntrySettings
    {
        return new EntrySettings(
            $settings->enabled,
            $settings->dateWindow,
            $settings->channels,
            $settings->audience,
            $content instanceof PageInterface ? $settings->homepageOf : [],
            $contentType->order->byPosition ? $settings->position : null,
        );
    }

    /**
     * The preset, its blocks and a starting point's layout upgraded as setono:cms:upgrade upgrades an editor preset's,
     * or null with the reasons to refuse it
     *
     * @param list<ImportRefusal> $refusals
     *
     * @throws InvalidExportException
     */
    private function readPreset(Preset $preset, array &$refusals): ?Preset
    {
        $uuid = (string) $preset->uuid;
        $title = (new ReadPreset($preset, false))->label();
        $startingPoint = $preset->startingPoint;
        if (null !== $startingPoint && !$this->contentTypes->has($startingPoint->contentType)) {
            $refusals[] = new ImportRefusal($uuid, new TranslatableMessage('setono_sylius_cms.import.unknown_content_type', ['%title%' => $title, '%content_type%' => $startingPoint->contentType]));

            return null;
        }

        $serializer = new StoredTreeSerializer();
        /** @var array<array-key, mixed> $tree */
        $tree = CanonicalJson::decode(CanonicalJson::encode($serializer->normalize(new StoredTree($startingPoint?->layout, $preset->blocks))));
        if (null === $startingPoint) {
            $upgraded = $this->upgrader->upgradeBlocks(is_array($tree['blocks'] ?? null) ? $tree['blocks'] : []);
            $tree['blocks'] = $upgraded->data;
        } else {
            $upgraded = $this->upgrader->upgradeTree($tree, $startingPoint->contentType);
            $tree = $upgraded->data;
        }

        if ([] !== $upgraded->newer) {
            array_push($refusals, ...self::newer($uuid, $title, $upgraded->newer));

            return null;
        }

        try {
            $tree = $serializer->denormalize($tree);
        } catch (InvalidStoredTreeException $e) {
            throw new InvalidExportException(sprintf('The preset %s is invalid: %s', $uuid, $e->getMessage()), previous: $e);
        }

        if (null !== $startingPoint && null !== $tree->layout && !$this->layouts->has($startingPoint->contentType, $tree->layout->code)) {
            $refusals[] = new ImportRefusal($uuid, new TranslatableMessage('setono_sylius_cms.import.unknown_layout', ['%title%' => $title, '%layout%' => $tree->layout->code]));
        }

        array_push($refusals, ...$this->unknownBlockTypes($uuid, $title, $tree->allBlocks()));

        return new Preset(
            $preset->name,
            $tree->blocks,
            $preset->category,
            $preset->description,
            null === $startingPoint ? null : new StartingPoint($startingPoint->contentType, $tree->layout),
            uuid: $uuid,
            changedAt: $preset->changedAt,
        );
    }

    /**
     * @param iterable<StoredBlock> $blocks
     *
     * @return list<ImportRefusal> one for each type the code doesn't have
     */
    private function unknownBlockTypes(string $uuid, string $title, iterable $blocks): array
    {
        $unknown = [];
        foreach ($blocks as $block) {
            if (!$this->blockTypes->has($block->type)) {
                $unknown[$block->type] = new ImportRefusal($uuid, new TranslatableMessage('setono_sylius_cms.import.unknown_block_type', ['%title%' => $title, '%type%' => $block->type]));
            }
        }

        return array_values($unknown);
    }

    /**
     * @param list<NewerVersion> $newer
     *
     * @return list<ImportRefusal>
     */
    private static function newer(string $uuid, string $title, array $newer): array
    {
        return array_map(static fn (NewerVersion $version): ImportRefusal => new ImportRefusal($uuid, new TranslatableMessage('setono_sylius_cms.import.newer_version', [
            '%title%' => $title,
            '%reason%' => (string) $version,
        ])), $newer);
    }

    /**
     * What names an entry before it's read: its title in its first translation that has one, or its UUID
     *
     * @param array<array-key, mixed> $data
     */
    private static function titleOf(string $uuid, array $data): string
    {
        try {
            foreach (EntryDenormalizer::translationValues($data, ['title']) as $values) {
                if (null !== $values['title'] && '' !== $values['title']) {
                    return $values['title'];
                }
            }
        } catch (InvalidEntryFormatException) {
        }

        return $uuid;
    }

    /**
     * The UUIDs of the records whose JSON files are right in the directory, such as entries/<uuid>.json
     *
     * @return list<string>
     */
    private static function uuids(ExportSourceInterface $source, string $directory): array
    {
        $uuids = [];
        foreach ($source->files($directory) as $file) {
            if (str_ends_with($file, '.json') && 'folders.json' !== $file) {
                $uuids[] = substr($file, 0, -5);
            }
        }

        return $uuids;
    }
}
