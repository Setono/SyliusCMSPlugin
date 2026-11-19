<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Transfer\Format;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Media\MediaKind;
use Setono\SyliusCMSPlugin\Model\MediaItem;
use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Transfer\Format\ExportedMediaFolder;
use Setono\SyliusCMSPlugin\Transfer\Format\ExportedMediaItem;
use Setono\SyliusCMSPlugin\Transfer\Format\ExportFormat;
use Setono\SyliusCMSPlugin\Transfer\Format\InvalidExportException;
use Setono\SyliusCMSPlugin\Transfer\Format\Manifest;

/**
 * The export format's files and paths. See "Export format" in docs/spec/transfer.md
 */
final class ExportFormatTest extends TestCase
{
    private const PAGE = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e';

    private const SNIPPET = '0193a5be-7255-7f00-8a1b-2c3d4e5f6a7b';

    private const PRESET = '0193a5be-7400-7d3e-8f4a-5b6c7d8e9f0a';

    private const IMAGE = '0193a5be-70aa-7c11-8e2d-1b2c3d4e5f60';

    private const VIDEO = '0193a5be-70ab-7c12-8e2d-1b2c3d4e5f61';

    /**
     * @test
     */
    public function it_writes_the_manifest_with_the_picked_records_sorted_and_the_time_in_utc(): void
    {
        $manifest = new Manifest('2.0.0', new \DateTimeImmutable('2026-11-13 16:00:00', new \DateTimeZone('Europe/Copenhagen')), [self::SNIPPET, self::PAGE, self::SNIPPET]);

        self::assertSame(<<<'JSON'
            {
                "format": 1,
                "plugin": "2.0.0",
                "exportedAt": "2026-11-13T15:00:00Z",
                "picked": {
                    "entries": [
                        "0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e",
                        "0193a5be-7255-7f00-8a1b-2c3d4e5f6a7b"
                    ],
                    "presets": [],
                    "media": []
                }
            }

            JSON, (new ExportFormat())->encodeManifest($manifest));
    }

    /**
     * @test
     */
    public function it_places_each_file_where_the_format_says(): void
    {
        self::assertSame('entries/' . self::PAGE . '.json', ExportFormat::entryPath(self::PAGE));
        self::assertSame('presets/' . self::PAGE . '.json', ExportFormat::presetPath(self::PAGE));
        self::assertSame('media/' . self::PAGE . '.json', ExportFormat::mediaItemPath(self::PAGE));
        self::assertSame('media/' . self::PAGE . '/Summer Sale!.JPEG', ExportFormat::mediaFilePath(self::PAGE, 'Summer Sale!.JPEG'));
    }

    /**
     * @test
     */
    public function it_names_the_directories_that_hold_one_file_per_record_and_reads_the_uuid_back_from_a_file_in_one(): void
    {
        foreach ([
            [ExportFormat::ENTRIES, ExportFormat::entryPath(self::PAGE)],
            [ExportFormat::PRESETS, ExportFormat::presetPath(self::PAGE)],
            [ExportFormat::MEDIA, ExportFormat::mediaItemPath(self::PAGE)],
        ] as [$directory, $path]) {
            self::assertSame($directory . '/' . basename($path), $path);
            self::assertSame(self::PAGE, ExportFormat::recordUuid($directory, basename($path)));
        }

        self::assertSame(ExportFormat::MEDIA . '/' . basename(ExportFormat::MEDIA_FOLDERS), ExportFormat::MEDIA_FOLDERS);
        self::assertNull(ExportFormat::recordUuid(ExportFormat::MEDIA, basename(ExportFormat::MEDIA_FOLDERS)), 'media/folders.json lists the folders, it is no media item');
        self::assertNull(ExportFormat::recordUuid(ExportFormat::ENTRIES, 'notes.txt'));
    }

    /**
     * @test
     */
    public function it_takes_only_a_plain_basename_for_a_media_file(): void
    {
        foreach (['autumn-banner.jpg', 'Summer Sale!.JPEG', '.htaccess', 'naïve café.png'] as $filename) {
            self::assertTrue(ExportFormat::isPlainFilename($filename), $filename);
        }

        foreach (['', '.', '..', '../secret.jpg', 'a/b.jpg', 'a\b.jpg', "tab\t.jpg", "nul\0.jpg", 'two..dots.jpg'] as $filename) {
            self::assertFalse(ExportFormat::isPlainFilename($filename), $filename);
        }

        $this->expectException(\InvalidArgumentException::class);
        ExportFormat::mediaFilePath(self::PAGE, '../secret.jpg');
    }

    /**
     * @test
     */
    public function it_names_a_media_file_by_its_stored_name_when_the_original_is_not_a_plain_basename(): void
    {
        $mediaItem = new MediaItem();
        $mediaItem->setOriginalFilename("Price\tlist.pdf");
        $mediaItem->setPath('0193a5be-70aa-7c11-8e2d-1b2c3d4e5f60/3f9a1c2b7d4e5a60/price-list.pdf');
        $mediaItem->setKind(MediaKind::Document);
        $mediaItem->setDimensions(800, 600);
        $mediaItem->setAltText('en_US', 'A price list');

        $exported = ExportedMediaItem::of($mediaItem);

        self::assertSame('price-list.pdf', $exported->filename);
        // Dimensions, alt text and the focal point are an image's only
        self::assertNull($exported->width);
        self::assertNull($exported->height);
        self::assertSame([], $exported->alt);
        self::assertNull($exported->focalPoint);
    }

    /**
     * @test
     */
    public function it_reads_each_file_of_the_committed_export_as_it_writes_it(): void
    {
        $format = new ExportFormat();

        $manifest = $format->decodeManifest(self::fixture(ExportFormat::MANIFEST));
        self::assertSame(1, $manifest->format);
        self::assertSame('2.0.0', $manifest->plugin);
        self::assertSame('2026-11-13T15:00:00Z', ExportFormat::dateTime($manifest->exportedAt));
        self::assertSame([self::PAGE], $manifest->entries);
        self::assertSame([self::PRESET], $manifest->presets);
        self::assertSame([], $manifest->media);
        self::assertSame(self::fixture(ExportFormat::MANIFEST), $format->encodeManifest($manifest));

        // The entry is read as CanonicalJson::decode() reads it, whose maps no longer keep the format's key order
        foreach ([self::PAGE, self::SNIPPET] as $uuid) {
            $file = self::fixture(ExportFormat::entryPath($uuid));
            self::assertEquals(CanonicalJson::decode($file), CanonicalJson::decode($format->encodeEntry($format->decodeEntry($file))), $uuid);
        }

        $snippet = $format->decodeEntry(self::fixture(ExportFormat::entryPath(self::SNIPPET)));
        self::assertIsArray($snippet->entry);
        self::assertSame(self::SNIPPET, $snippet->entry['uuid'] ?? null);
        self::assertSame('2026-11-12T09:30:00Z', ExportFormat::dateTime($snippet->changedAt));
        self::assertSame(['WEB'], $snippet->settings->channels);
        self::assertCount(1, $snippet->placements);
        self::assertSame('product.after_price', $snippet->placements[0]->slot);
        self::assertSame(['0193a5be-7301-7c2d-9e3f-4a5b6c7d8e9f'], $snippet->placements[0]->preceding);

        $preset = $format->decodePreset(self::fixture(ExportFormat::presetPath(self::PRESET)));
        self::assertSame(self::PRESET, $preset->uuid);
        self::assertSame('Autumn hero', $preset->name);
        self::assertSame(self::fixture(ExportFormat::presetPath(self::PRESET)), $format->encodePreset($preset));

        $folders = $format->decodeMediaFolders(self::fixture(ExportFormat::MEDIA_FOLDERS));
        self::assertEquals([
            new ExportedMediaFolder('0193a5be-7500-7e4f-9a5b-6c7d8e9f0a1b', 'Campaigns', null),
            new ExportedMediaFolder('0193a5be-7501-7f5a-8b6c-7d8e9f0a1b2c', 'Autumn', '0193a5be-7500-7e4f-9a5b-6c7d8e9f0a1b'),
        ], $folders);
        self::assertSame(self::fixture(ExportFormat::MEDIA_FOLDERS), $format->encodeMediaFolders($folders));

        $image = $format->decodeMediaItem(self::fixture(ExportFormat::mediaItemPath(self::IMAGE)));
        self::assertSame(MediaKind::Image, $image->kind);
        self::assertSame('430515c3658b5bad70fad73a90fb25d761f2fd988f8ad646f9e21b8d9bcce2f1', $image->checksum);
        self::assertSame(['en_US' => 'A woman in a red coat among autumn leaves'], $image->alt);
        foreach ([self::IMAGE, self::VIDEO] as $uuid) {
            $file = self::fixture(ExportFormat::mediaItemPath($uuid));
            self::assertSame($file, $format->encodeMediaItem($format->decodeMediaItem($file)), $uuid);
        }
    }

    /**
     * @test
     */
    public function it_refuses_a_format_it_does_not_know_and_files_that_are_not_in_the_format(): void
    {
        $format = new ExportFormat();
        $settings = '"changedAt": "2026-11-12T10:15:00Z", "settings": {"enabled": true}';
        $preset = (string) preg_replace('/"blocks": \[.*\]/s', '"blocks": {}', self::fixture(ExportFormat::presetPath(self::PRESET)));
        $refused = [
            'a manifest of another format' => [static fn () => $format->decodeManifest('{"format": 2, "plugin": "3.0.0", "exportedAt": "2027-01-01T00:00:00Z", "picked": {"entries": [], "presets": [], "media": []}}'), 'The export has the format 2, but this version of the plugin reads format 1'],
            'a manifest without the plugin version' => [static fn () => $format->decodeManifest('{"format": 1, "exportedAt": "2026-11-13T15:00:00Z", "picked": {"entries": [], "presets": [], "media": []}}'), 'The manifest must name the plugin version that wrote it'],
            'a manifest without picked records' => [static fn () => $format->decodeManifest('{"format": 1, "plugin": "2.0.0", "exportedAt": "2026-11-13T15:00:00Z"}'), 'The manifest\'s picked records must be a JSON object'],
            'JSON that does not parse' => [static fn () => $format->decodeManifest('{"format": 1,'), 'manifest.json isn\'t valid JSON: Syntax error'],
            'an entry without its entry' => [static fn () => $format->decodeEntry('{' . $settings . ', "placements": []}'), 'The entry\'s file must hold the entry in the entry format'],
            'an entry with a date that is not one' => [static fn () => $format->decodeEntry('{"changedAt": "yesterday", "settings": {}, "placements": [], "entry": {}}'), 'The entry\'s changedAt must be a date and time in UTC, such as 2026-11-13T15:00:00Z'],
            'an entry whose settings are not settings' => [static fn () => $format->decodeEntry('{"changedAt": "2026-11-12T10:15:00Z", "settings": {"enabled": "yes"}, "placements": [], "entry": {}}'), 'The entry\'s settings are invalid: "enabled" must be true or false'],
            'a placement without a slot' => [static fn () => $format->decodeEntry('{' . $settings . ', "placements": [{"uuid": "0193a5be-7300-7b1c-8d2e-3f4a5b6c7d8e", "scope": [], "preceding": []}], "entry": {"uuid": "' . self::SNIPPET . '"}}'), 'A placement must name its slot'],
            'a placement whose UUID is not one' => [static fn () => $format->decodeEntry('{' . $settings . ', "placements": [{"uuid": "1", "slot": "product.after_price", "scope": [], "preceding": []}], "entry": {"uuid": "' . self::SNIPPET . '"}}'), 'A placement\'s UUID must be a UUID'],
            'a code preset' => [static fn () => $format->decodePreset('{"format": 1, "name": "Hero", "category": null, "description": null, "thumbnail": null, "startingPoint": null, "blocks": []}'), 'An export holds editor presets, which have a UUID and a changedAt'],
            'a preset whose blocks are not a list' => [static fn () => $format->decodePreset($preset), 'The preset is invalid: The preset must have a list of blocks'],
            'folders that are not a list' => [static fn () => $format->decodeMediaFolders('{"uuid": "0193a5be-7500-7e4f-9a5b-6c7d8e9f0a1b"}'), 'The media folders must be a JSON list'],
            'a folder without a name' => [static fn () => $format->decodeMediaFolders('[{"uuid": "0193a5be-7500-7e4f-9a5b-6c7d8e9f0a1b", "name": "", "parent": null}]'), 'A media folder must have a name'],
            'a folder before its parent' => [static fn () => $format->decodeMediaFolders('[{"uuid": "0193a5be-7501-7f5a-8b6c-7d8e9f0a1b2c", "name": "Autumn", "parent": "0193a5be-7500-7e4f-9a5b-6c7d8e9f0a1b"}, {"uuid": "0193a5be-7500-7e4f-9a5b-6c7d8e9f0a1b", "name": "Campaigns", "parent": null}]'), 'The parent of the media folder 0193a5be-7501-7f5a-8b6c-7d8e9f0a1b2c must be listed before it'],
            'a folder whose parent is not listed' => [static fn () => $format->decodeMediaFolders('[{"uuid": "0193a5be-7501-7f5a-8b6c-7d8e9f0a1b2c", "name": "Autumn", "parent": "0193a5be-7500-7e4f-9a5b-6c7d8e9f0a1b"}]'), 'The parent of the media folder 0193a5be-7501-7f5a-8b6c-7d8e9f0a1b2c must be listed before it'],
            'a folder listed twice' => [static fn () => $format->decodeMediaFolders('[{"uuid": "0193a5be-7500-7e4f-9a5b-6c7d8e9f0a1b", "name": "Campaigns", "parent": null}, {"uuid": "0193a5be-7500-7e4f-9a5b-6c7d8e9f0a1b", "name": "Campaigns", "parent": null}]'), 'The media folder 0193a5be-7500-7e4f-9a5b-6c7d8e9f0a1b is listed twice'],
            'a media item of a kind there is none of' => [static fn () => $format->decodeMediaItem(str_replace('"image"', '"sound"', self::fixture(ExportFormat::mediaItemPath(self::IMAGE)))), 'A media item\'s kind must be image, video or document'],
            'a media item whose checksum is not one' => [static fn () => $format->decodeMediaItem(str_replace('sha256:430515', 'md5:430515', self::fixture(ExportFormat::mediaItemPath(self::IMAGE)))), 'A media item\'s checksum must be sha256: and the lowercase hex SHA-256 of its file'],
            'a media item whose focal point is outside it' => [static fn () => $format->decodeMediaItem(str_replace('"x": 0.42', '"x": 1.5', self::fixture(ExportFormat::mediaItemPath(self::IMAGE)))), 'A focal point\'s x and y must be from 0 to 1, not 1.5 and 0.35'],
        ];

        foreach ($refused as $what => [$decode, $message]) {
            try {
                $decode();
                self::fail(sprintf('It read %s', $what));
            } catch (InvalidExportException $e) {
                self::assertSame($message, $e->getMessage(), $what);
            }
        }
    }

    /**
     * An export holds what editors made, which a code preset isn't
     *
     * @test
     */
    public function it_writes_only_an_editor_preset(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('An export holds editor presets, which have a UUID and a changedAt'));

        (new ExportFormat())->encodePreset(new Preset('Hero', [], code: 'hero'));
    }

    private static function fixture(string $path): string
    {
        return (string) file_get_contents(__DIR__ . '/../../../Fixture/export/' . $path);
    }
}
