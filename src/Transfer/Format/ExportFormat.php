<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Format;

use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Json\ListedObject;
use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Preset\PresetSerializer;
use Setono\SyliusCMSPlugin\Publishing\EntrySettingsNormalizer;

/**
 * The export format, which is a contract: exports are kept and committed as fixtures. It's the one place that knows
 * where each file of an export sits and what JSON it holds, so the export writes, and an import reads, every file
 * through here. All of it is canonical JSON, pretty-printed with four spaces and ending with a newline, keyed in the
 * order the spec lists. See "Export format" in docs/spec/transfer.md
 *
 * @internal
 */
final class ExportFormat
{
    /** The export format's version. A change to the format itself bumps it, and an import refuses a version it doesn't know */
    public const VERSION = 1;

    public const MANIFEST = 'manifest.json';

    public const MEDIA_FOLDERS = 'media/folders.json';

    private const CHECKSUM_PREFIX = 'sha256:';

    public static function entryPath(string $uuid): string
    {
        return sprintf('entries/%s.json', $uuid);
    }

    public static function presetPath(string $uuid): string
    {
        return sprintf('presets/%s.json', $uuid);
    }

    public static function mediaItemPath(string $uuid): string
    {
        return sprintf('media/%s.json', $uuid);
    }

    /**
     * Where a media item's original file sits, beside its JSON
     *
     * @throws \InvalidArgumentException when the filename isn't a plain basename
     */
    public static function mediaFilePath(string $uuid, string $filename): string
    {
        if (!self::isPlainFilename($filename)) {
            throw new \InvalidArgumentException(sprintf('The filename "%s" isn\'t a plain basename', $filename));
        }

        return sprintf('media/%s/%s', $uuid, $filename);
    }

    /**
     * Whether a media item's filename is one an export may hold: a plain basename, with no "/", "\", ".." or control
     * characters. An import refuses any other
     */
    public static function isPlainFilename(string $filename): bool
    {
        return '' !== $filename && '.' !== $filename && 1 !== preg_match('#[/\\\\\x00-\x1F\x7F]|\.\.#', $filename);
    }

    /**
     * A date and time as the format writes it: in UTC, to the second, such as 2026-11-13T15:00:00Z
     */
    public static function dateTime(\DateTimeImmutable $dateTime): string
    {
        return $dateTime->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }

    public function encodeManifest(Manifest $manifest): string
    {
        return CanonicalJson::encodeFile(new ListedObject([
            'format' => $manifest->format,
            'plugin' => $manifest->plugin,
            'exportedAt' => self::dateTime($manifest->exportedAt),
            'picked' => new ListedObject([
                'entries' => self::sorted($manifest->entries),
                'presets' => self::sorted($manifest->presets),
                'media' => self::sorted($manifest->media),
            ]),
        ]));
    }

    public function encodeEntry(ExportedEntry $entry): string
    {
        return CanonicalJson::encodeFile(new ListedObject([
            'changedAt' => self::dateTime($entry->changedAt),
            'settings' => (new EntrySettingsNormalizer())->normalize($entry->settings),
            'placements' => array_map(static fn (ExportedPlacement $placement): ListedObject => new ListedObject([
                'uuid' => $placement->uuid,
                'slot' => $placement->slot,
                'scope' => array_map(static fn (array $condition): ListedObject => new ListedObject([
                    'kind' => $condition['kind'] ?? null,
                    'negate' => $condition['negate'] ?? false,
                    'params' => (object) (is_array($condition['params'] ?? null) ? $condition['params'] : []),
                ]), $placement->scope),
                'preceding' => $placement->preceding,
            ]), $entry->placements),
            'entry' => $entry->entry,
        ]));
    }

    /**
     * An editor preset, in the preset file format code presets use, with its UUID and changedAt
     */
    public function encodePreset(Preset $preset): string
    {
        if (null === $preset->uuid || null === $preset->changedAt) {
            throw new \InvalidArgumentException('An export holds editor presets, which have a UUID and a changedAt');
        }

        return (new PresetSerializer())->toJson($preset);
    }

    /**
     * @param list<ExportedMediaFolder> $folders parents before their children
     */
    public function encodeMediaFolders(array $folders): string
    {
        return CanonicalJson::encodeFile(array_map(static fn (ExportedMediaFolder $folder): ListedObject => new ListedObject([
            'uuid' => $folder->uuid,
            'name' => $folder->name,
            'parent' => $folder->parent,
        ]), $folders));
    }

    public function encodeMediaItem(ExportedMediaItem $mediaItem): string
    {
        return CanonicalJson::encodeFile(new ListedObject([
            'uuid' => $mediaItem->uuid,
            'folder' => $mediaItem->folder,
            'kind' => $mediaItem->kind->value,
            'name' => $mediaItem->name,
            'filename' => $mediaItem->filename,
            'mimeType' => $mediaItem->mimeType,
            'size' => $mediaItem->size,
            'width' => $mediaItem->width,
            'height' => $mediaItem->height,
            'credit' => $mediaItem->credit,
            'alt' => (object) $mediaItem->alt,
            'focalPoint' => null === $mediaItem->focalPoint ? null : new ListedObject(['x' => $mediaItem->focalPoint->x, 'y' => $mediaItem->focalPoint->y]),
            'poster' => $mediaItem->poster,
            'checksum' => self::CHECKSUM_PREFIX . $mediaItem->checksum,
            'changedAt' => self::dateTime($mediaItem->changedAt),
        ]));
    }

    /**
     * @param list<string> $uuids
     *
     * @return list<string>
     */
    private static function sorted(array $uuids): array
    {
        $uuids = array_values(array_unique($uuids));
        sort($uuids, \SORT_STRING);

        return $uuids;
    }
}
