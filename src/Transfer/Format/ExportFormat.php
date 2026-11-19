<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Format;

use Setono\SyliusCMSPlugin\Condition\StoredCondition;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Json\ListedObject;
use Setono\SyliusCMSPlugin\Media\FocalPoint;
use Setono\SyliusCMSPlugin\Media\MediaKind;
use Setono\SyliusCMSPlugin\Preset\InvalidPresetException;
use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Preset\PresetSerializer;
use Setono\SyliusCMSPlugin\Publishing\EntrySettings;
use Setono\SyliusCMSPlugin\Publishing\EntrySettingsNormalizer;
use Setono\SyliusCMSPlugin\Publishing\InvalidEntrySettingsException;
use Setono\SyliusCMSPlugin\Targeting\Audience;
use Setono\SyliusCMSPlugin\Targeting\AudienceMode;
use Setono\SyliusCMSPlugin\Targeting\DateWindow;

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

    /** The directory that holds each entry's file, <uuid>.json */
    public const ENTRIES = 'entries';

    /** The directory that holds each editor preset's file, <uuid>.json */
    public const PRESETS = 'presets';

    /** The directory that holds each media item's file, <uuid>.json, its original file in <uuid>/ and the folders' file */
    public const MEDIA = 'media';

    public const MEDIA_FOLDERS = self::MEDIA . '/folders.json';

    private const RECORD_EXTENSION = '.json';

    private const CHECKSUM_PREFIX = 'sha256:';

    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    public static function entryPath(string $uuid): string
    {
        return self::recordPath(self::ENTRIES, $uuid);
    }

    public static function presetPath(string $uuid): string
    {
        return self::recordPath(self::PRESETS, $uuid);
    }

    public static function mediaItemPath(string $uuid): string
    {
        return self::recordPath(self::MEDIA, $uuid);
    }

    /**
     * The UUID of the record whose file sits in one of the record directories (ENTRIES, PRESETS, MEDIA) under the
     * given name, as entryPath(), presetPath() and mediaItemPath() write it, or null when the file is no record's,
     * such as media/folders.json
     */
    public static function recordUuid(string $directory, string $file): ?string
    {
        if (!str_ends_with($file, self::RECORD_EXTENSION) || self::MEDIA_FOLDERS === $directory . '/' . $file) {
            return null;
        }

        return substr($file, 0, -strlen(self::RECORD_EXTENSION));
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

        return sprintf('%s/%s/%s', self::MEDIA, $uuid, $filename);
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
     * @throws InvalidExportException when it isn't a manifest of the format this code reads
     */
    public function decodeManifest(string $json): Manifest
    {
        $data = self::map(self::decode($json, self::MANIFEST), 'The manifest');
        $format = $data['format'] ?? null;
        if (self::VERSION !== $format) {
            throw new InvalidExportException(sprintf('The export has the format %s, but this version of the plugin reads format %d', json_encode($format), self::VERSION));
        }

        $plugin = $data['plugin'] ?? null;
        if (!is_string($plugin)) {
            throw new InvalidExportException('The manifest must name the plugin version that wrote it');
        }

        $picked = self::map($data['picked'] ?? null, 'The manifest\'s picked records');

        return new Manifest(
            $plugin,
            self::parseDateTime($data['exportedAt'] ?? null, 'The manifest\'s exportedAt'),
            self::uuids($picked['entries'] ?? null, 'The manifest\'s picked entries'),
            self::uuids($picked['presets'] ?? null, 'The manifest\'s picked presets'),
            self::uuids($picked['media'] ?? null, 'The manifest\'s picked media items'),
        );
    }

    /**
     * The entry itself is read as CanonicalJson::decode() reads it, for EntryDenormalizer, after the upgraders, to
     * check further
     *
     * @throws InvalidExportException when it isn't an entry's file of the export format
     */
    public function decodeEntry(string $json): ExportedEntry
    {
        $data = self::map(self::decode($json, 'An entry'), 'An entry\'s file');
        $changedAt = self::parseDateTime($data['changedAt'] ?? null, 'The entry\'s changedAt');

        try {
            $settings = (new EntrySettingsNormalizer())->denormalize($data['settings'] ?? null, new EntrySettings(true, new DateWindow(null, null), [], new Audience(AudienceMode::Everyone)));
        } catch (InvalidEntrySettingsException $e) {
            throw new InvalidExportException(sprintf('The entry\'s settings are invalid: %s', $e->getMessage()), previous: $e);
        }

        $placements = [];
        foreach (self::list($data['placements'] ?? null, 'The entry\'s placements') as $placement) {
            $placements[] = self::readPlacement($placement);
        }

        $entry = $data['entry'] ?? null;
        if (!is_array($entry) || array_is_list($entry)) {
            throw new InvalidExportException('The entry\'s file must hold the entry in the entry format');
        }

        return new ExportedEntry($changedAt, $settings, $placements, $entry);
    }

    /**
     * @throws InvalidExportException when it isn't an editor preset in the preset file format
     */
    public function decodePreset(string $json): Preset
    {
        try {
            $preset = (new PresetSerializer())->fromJson($json);
        } catch (InvalidPresetException $e) {
            throw new InvalidExportException(sprintf('The preset is invalid: %s', $e->getMessage()), previous: $e);
        }

        if ($preset->isFromCode()) {
            throw new InvalidExportException('An export holds editor presets, which have a UUID and a changedAt');
        }

        return $preset;
    }

    /**
     * @return list<ExportedMediaFolder> parents before their children, as the file lists them
     *
     * @throws InvalidExportException when a folder's parent isn't listed before it, or a folder is listed twice
     */
    public function decodeMediaFolders(string $json): array
    {
        $folders = [];
        foreach (self::list(self::decode($json, self::MEDIA_FOLDERS), 'The media folders') as $folder) {
            $folder = self::map($folder, 'A media folder');
            $name = $folder['name'] ?? null;
            if (!is_string($name) || '' === $name) {
                throw new InvalidExportException('A media folder must have a name');
            }

            $uuid = self::uuid($folder['uuid'] ?? null, 'A media folder\'s UUID');
            $parent = $folder['parent'] ?? null;
            $parent = null === $parent ? null : self::uuid($parent, 'A media folder\'s parent');
            if (isset($folders[$uuid])) {
                throw new InvalidExportException(sprintf('The media folder %s is listed twice', $uuid));
            }

            if (null !== $parent && !isset($folders[$parent])) {
                throw new InvalidExportException(sprintf('The parent of the media folder %s must be listed before it', $uuid));
            }

            $folders[$uuid] = new ExportedMediaFolder($uuid, $name, $parent);
        }

        return array_values($folders);
    }

    /**
     * Reads the values as they're written. An import reads a file's MIME type, size and dimensions from the file
     * itself, and checks its checksum (Import\ImportedFileChecker)
     *
     * @throws InvalidExportException
     */
    public function decodeMediaItem(string $json): ExportedMediaItem
    {
        $data = self::map(self::decode($json, 'A media item'), 'A media item\'s file');
        $kind = MediaKind::tryFrom(is_string($data['kind'] ?? null) ? $data['kind'] : '') ?? throw new InvalidExportException('A media item\'s kind must be image, video or document');
        $checksum = $data['checksum'] ?? null;
        if (!is_string($checksum) || 1 !== preg_match('/^' . self::CHECKSUM_PREFIX . '([0-9a-f]{64})$/', $checksum, $matches)) {
            throw new InvalidExportException('A media item\'s checksum must be sha256: and the lowercase hex SHA-256 of its file');
        }

        $alt = [];
        foreach (self::mapOrEmpty($data['alt'] ?? null, 'A media item\'s alt text') as $localeCode => $text) {
            $alt[(string) $localeCode] = is_string($text) ? $text : throw new InvalidExportException('A media item\'s alt text must be text by locale');
        }

        $focalPoint = $data['focalPoint'] ?? null;
        if (null !== $focalPoint) {
            $focalPoint = self::map($focalPoint, 'A media item\'s focal point');

            try {
                $focalPoint = new FocalPoint(self::number($focalPoint['x'] ?? null), self::number($focalPoint['y'] ?? null));
            } catch (\InvalidArgumentException $e) {
                throw new InvalidExportException($e->getMessage(), previous: $e);
            }
        }

        $folder = $data['folder'] ?? null;
        $poster = $data['poster'] ?? null;

        return new ExportedMediaItem(
            self::uuid($data['uuid'] ?? null, 'A media item\'s UUID'),
            null === $folder ? null : self::uuid($folder, 'A media item\'s folder'),
            $kind,
            self::string($data['name'] ?? null, 'A media item\'s name'),
            self::string($data['filename'] ?? null, 'A media item\'s filename'),
            self::string($data['mimeType'] ?? null, 'A media item\'s MIME type'),
            self::nullableInt($data['size'] ?? null, 'A media item\'s size') ?? throw new InvalidExportException('A media item must have its size'),
            self::nullableInt($data['width'] ?? null, 'A media item\'s width'),
            self::nullableInt($data['height'] ?? null, 'A media item\'s height'),
            null === ($data['credit'] ?? null) ? null : self::string($data['credit'], 'A media item\'s credit'),
            $alt,
            $focalPoint,
            null === $poster ? null : self::uuid($poster, 'A media item\'s poster'),
            $matches[1],
            self::parseDateTime($data['changedAt'] ?? null, 'A media item\'s changedAt'),
        );
    }

    private static function recordPath(string $directory, string $uuid): string
    {
        return $directory . '/' . $uuid . self::RECORD_EXTENSION;
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

    /**
     * @throws InvalidExportException
     */
    private static function decode(string $json, string $what): mixed
    {
        try {
            return CanonicalJson::decode($json);
        } catch (\JsonException $e) {
            throw new InvalidExportException(sprintf('%s isn\'t valid JSON: %s', $what, $e->getMessage()), previous: $e);
        }
    }

    /**
     * @return array<string, mixed>
     *
     * @throws InvalidExportException when the value isn't a JSON object with keys, as CanonicalJson::decode() reads one
     */
    private static function map(mixed $value, string $what): array
    {
        if (!is_array($value) || array_is_list($value)) {
            throw new InvalidExportException(sprintf('%s must be a JSON object', $what));
        }

        /** @var array<string, mixed> $map */
        $map = $value;

        return $map;
    }

    /**
     * A JSON object that may be empty, which CanonicalJson::decode() reads as \stdClass
     *
     * @return array<array-key, mixed>
     *
     * @throws InvalidExportException
     */
    private static function mapOrEmpty(mixed $value, string $what): array
    {
        return $value instanceof \stdClass ? get_object_vars($value) : self::map($value, $what);
    }

    /**
     * @return list<mixed>
     *
     * @throws InvalidExportException
     */
    private static function list(mixed $value, string $what): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new InvalidExportException(sprintf('%s must be a JSON list', $what));
        }

        return $value;
    }

    /**
     * @throws InvalidExportException
     */
    private static function uuid(mixed $value, string $what): string
    {
        if (!is_string($value) || 1 !== preg_match(self::UUID_PATTERN, $value)) {
            throw new InvalidExportException(sprintf('%s must be a UUID', $what));
        }

        return $value;
    }

    /**
     * @return list<string>
     *
     * @throws InvalidExportException
     */
    private static function uuids(mixed $value, string $what): array
    {
        return array_map(static fn (mixed $uuid): string => self::uuid($uuid, sprintf('Each of %s', lcfirst($what))), self::list($value, $what));
    }

    /**
     * @throws InvalidExportException
     */
    private static function string(mixed $value, string $what): string
    {
        return is_string($value) && '' !== $value ? $value : throw new InvalidExportException(sprintf('%s must be text', $what));
    }

    /**
     * @throws InvalidExportException
     */
    private static function nullableInt(mixed $value, string $what): ?int
    {
        return null === $value || (is_int($value) && $value >= 0) ? $value : throw new InvalidExportException(sprintf('%s must be a whole number, or null', $what));
    }

    /**
     * @throws InvalidExportException
     */
    private static function number(mixed $value): float
    {
        return is_int($value) || is_float($value) ? (float) $value : throw new InvalidExportException('A media item\'s focal point must have x and y from 0 to 1');
    }

    /**
     * A date and time as the format writes it, in UTC to the second
     *
     * @throws InvalidExportException
     */
    public static function parseDateTime(mixed $value, string $what): \DateTimeImmutable
    {
        $dateTime = is_string($value) ? \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $value, new \DateTimeZone('UTC')) : false;
        if (false === $dateTime || self::dateTime($dateTime) !== $value) {
            throw new InvalidExportException(sprintf('%s must be a date and time in UTC, such as 2026-11-13T15:00:00Z', $what));
        }

        return $dateTime;
    }

    /**
     * @throws InvalidExportException
     */
    private static function readPlacement(mixed $value): ExportedPlacement
    {
        $placement = self::map($value, 'A placement');
        $slot = $placement['slot'] ?? null;
        if (!is_string($slot) || '' === $slot) {
            throw new InvalidExportException('A placement must name its slot');
        }

        $scope = [];
        foreach (self::list($placement['scope'] ?? null, 'A placement\'s scope') as $condition) {
            $scope[] = StoredCondition::fromValue($condition)?->toArray() ?? throw new InvalidExportException('A placement\'s scope must be a list of conditions in the stored condition format');
        }

        return new ExportedPlacement(
            self::uuid($placement['uuid'] ?? null, 'A placement\'s UUID'),
            $slot,
            $scope,
            self::uuids($placement['preceding'] ?? null, 'A placement\'s preceding placements'),
        );
    }
}
