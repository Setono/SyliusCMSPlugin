<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Transfer\Format;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Media\MediaKind;
use Setono\SyliusCMSPlugin\Model\MediaItem;
use Setono\SyliusCMSPlugin\Transfer\Format\ExportedMediaItem;
use Setono\SyliusCMSPlugin\Transfer\Format\ExportFormat;
use Setono\SyliusCMSPlugin\Transfer\Format\Manifest;

/**
 * The export format's files and paths. See "Export format" in docs/spec/transfer.md
 */
final class ExportFormatTest extends TestCase
{
    private const PAGE = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e';

    private const SNIPPET = '0193a5be-7255-7f00-8a1b-2c3d4e5f6a7b';

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
}
