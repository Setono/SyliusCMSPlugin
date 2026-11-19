<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Media;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Media\CheckedFile;
use Setono\SyliusCMSPlugin\Media\FocalPoint;
use Setono\SyliusCMSPlugin\Media\MediaKind;
use Setono\SyliusCMSPlugin\Model\MediaItem;

/**
 * A checked file is what an upload, a replace or an import points a media item at, with what was read from the file
 * itself. See "Uploading" and "Replacing a file" in docs/spec/media.md
 */
final class CheckedFileTest extends TestCase
{
    /**
     * @test
     */
    public function it_points_a_media_item_at_the_file_with_what_was_read_from_it(): void
    {
        $mediaItem = new MediaItem();
        $mediaItem->setName('Autumn banner');
        $mediaItem->setFocalPoint(new FocalPoint(0.42, 0.35));
        $checked = new CheckedFile(MediaKind::Image, 'image/webp', 'webp', '/tmp/checked', 2048, 1200, 500, str_repeat('c', 64));

        $checked->applyTo($mediaItem, 'Autumn banner.webp', '0193a5be-70aa-7c11-8e2d-1b2c3d4e5f60/3f9a1c2b7d4e5a60/autumn-banner.webp');

        self::assertSame('Autumn banner.webp', $mediaItem->getOriginalFilename());
        self::assertSame('0193a5be-70aa-7c11-8e2d-1b2c3d4e5f60/3f9a1c2b7d4e5a60/autumn-banner.webp', $mediaItem->getPath());
        self::assertSame('image/webp', $mediaItem->getMimeType());
        self::assertSame(2048, $mediaItem->getSize());
        self::assertSame([1200, 500], [$mediaItem->getWidth(), $mediaItem->getHeight()]);
        self::assertSame(str_repeat('c', 64), $mediaItem->getChecksum());
        // Its metadata stays
        self::assertSame('Autumn banner', $mediaItem->getName());
        self::assertEquals(new FocalPoint(0.42, 0.35), $mediaItem->getFocalPoint());
    }
}
