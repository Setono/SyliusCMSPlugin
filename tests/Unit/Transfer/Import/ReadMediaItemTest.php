<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Transfer\Import;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Media\CheckedFile;
use Setono\SyliusCMSPlugin\Media\FocalPoint;
use Setono\SyliusCMSPlugin\Media\MediaKind;
use Setono\SyliusCMSPlugin\Model\MediaFolder;
use Setono\SyliusCMSPlugin\Model\MediaItem;
use Setono\SyliusCMSPlugin\Transfer\Format\ExportedMediaItem;
use Setono\SyliusCMSPlugin\Transfer\Import\ReadMediaItem;

/**
 * A media item of the export is identical to the target's when the target holds the same file and the metadata the
 * import would write, so the import skips it and it's never a conflict. What the JSON says of the file isn't read. See
 * "What an import does" and "Stale records" in docs/spec/transfer.md
 */
final class ReadMediaItemTest extends TestCase
{
    private const UUID = '0193a5be-70aa-7c11-8e2d-1b2c3d4e5f60';

    private const FOLDER = '0193a5be-7501-7f5a-8b6c-7d8e9f0a1b2c';

    private const POSTER = '0193a5be-70ac-7c13-8e2d-1b2c3d4e5f62';

    private const CHECKSUM = '430515c3658b5bad70fad73a90fb25d761f2fd988f8ad646f9e21b8d9bcce2f1';

    /**
     * @test
     */
    public function it_is_identical_to_a_target_holding_the_same_file_and_metadata_whatever_the_json_says_of_the_file(): void
    {
        $read = self::read(mimeType: 'image/jpeg', size: 1, width: 1, height: 1);

        self::assertTrue($read->isIdenticalTo(self::target()));
    }

    /**
     * @test
     */
    public function it_differs_from_a_target_holding_another_file(): void
    {
        $target = self::target();
        $target->setChecksum(str_repeat('0', 64));

        self::assertFalse(self::read()->isIdenticalTo($target));
    }

    /**
     * @test
     */
    public function it_differs_from_a_target_with_other_metadata(): void
    {
        $changes = [
            'name' => static fn (MediaItem $target) => $target->setName('Autumn banner, edited here'),
            'folder' => static fn (MediaItem $target) => $target->setFolder(null),
            'filename' => static fn (MediaItem $target) => $target->setOriginalFilename('banner.png'),
            'credit' => static fn (MediaItem $target) => $target->setCredit('Photo: Studio North'),
            'alt text' => static fn (MediaItem $target) => $target->setAltText('da_DK', 'En kvinde i rød frakke'),
            'focal point' => static fn (MediaItem $target) => $target->setFocalPoint(new FocalPoint(0.5, 0.5)),
            'kind' => static fn (MediaItem $target) => $target->setKind(MediaKind::Document),
        ];

        foreach ($changes as $what => $change) {
            $target = self::target();
            $change($target);

            self::assertFalse(self::read()->isIdenticalTo($target), $what);
        }
    }

    /**
     * @test
     */
    public function it_compares_a_videos_poster_by_uuid(): void
    {
        $poster = new MediaItem();
        $poster->setUuid(self::POSTER);
        $target = self::target(MediaKind::Video);
        $target->setPoster($poster);

        self::assertTrue(self::read(MediaKind::Video, poster: self::POSTER)->isIdenticalTo($target));
        self::assertFalse(self::read(MediaKind::Video)->isIdenticalTo($target));
    }

    /**
     * @test
     */
    public function it_takes_only_the_metadata_the_files_kind_has(): void
    {
        // An image's metadata in the JSON of what's a document: a document has no alt text or focal point
        $read = self::read(MediaKind::Document, poster: self::POSTER);

        self::assertSame([], $read->altTexts());
        self::assertNull($read->focalPoint());
        self::assertNull($read->poster());
        self::assertTrue($read->isIdenticalTo(self::target(MediaKind::Document)));

        // An image without a focal point in the JSON has the default
        self::assertEquals(new FocalPoint(0.5, 0.5), self::read(focalPoint: null)->focalPoint());
    }

    private static function target(MediaKind $kind = MediaKind::Image): MediaItem
    {
        $folder = new MediaFolder();
        $folder->setUuid(self::FOLDER);

        $target = new MediaItem();
        $target->setUuid(self::UUID);
        $target->setKind($kind);
        $target->setFolder($folder);
        $target->setName('Autumn banner');
        $target->setOriginalFilename('autumn-banner.png');
        $target->setPath(self::UUID . '/3f9a1c2b7d4e5a60/autumn-banner.png');
        $target->setMimeType('image/png');
        $target->setSize(130);
        $target->setChecksum(self::CHECKSUM);
        if (MediaKind::Image === $kind) {
            $target->setDimensions(48, 20);
            $target->setAltText('en_US', 'A woman in a red coat among autumn leaves');
            $target->setFocalPoint(new FocalPoint(0.42, 0.35));
        }

        return $target;
    }

    private static function read(
        MediaKind $kind = MediaKind::Image,
        string $mimeType = 'image/png',
        int $size = 130,
        ?int $width = 48,
        ?int $height = 20,
        ?FocalPoint $focalPoint = new FocalPoint(0.42, 0.35),
        ?string $poster = null,
    ): ReadMediaItem {
        $exported = new ExportedMediaItem(
            self::UUID,
            self::FOLDER,
            MediaKind::Image,
            'Autumn banner',
            'autumn-banner.png',
            $mimeType,
            $size,
            $width,
            $height,
            null,
            ['en_US' => 'A woman in a red coat among autumn leaves'],
            $focalPoint,
            $poster,
            self::CHECKSUM,
            new \DateTimeImmutable('2026-11-11 12:00:00', new \DateTimeZone('UTC')),
        );

        return new ReadMediaItem($exported, new CheckedFile($kind, 'image/png', 'png', '/tmp/checked.png', 130, 48, 20, self::CHECKSUM), false, '/tmp/copy');
    }
}
