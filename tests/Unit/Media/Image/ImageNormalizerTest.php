<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Media\Image;

use Imagine\Gd\Imagine;
use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Media\Image\ImageNormalizer;
use Setono\SyliusCMSPlugin\Media\UnreadableFileException;

/**
 * On upload, the plugin applies the EXIF orientation and strips embedded metadata from the stored original, so location
 * and device data never leak. The others are stored as uploaded. See "EXIF" in docs/spec/media.md
 */
final class ImageNormalizerTest extends TestCase
{
    /**
     * The fixture is 40x20 pixels, left half red and right half blue, stored with EXIF orientation 6 (shown turned 90
     * degrees clockwise), the camera's make, GPS coordinates, an XMP packet and two comments
     */
    private const ROTATED_WITH_LOCATION = __DIR__ . '/../../../Fixture/media/rotated-with-location.jpg';

    /**
     * @test
     */
    public function it_applies_the_exif_orientation_of_a_jpeg_and_strips_its_metadata(): void
    {
        $jpeg = self::normalizer()->normalize((string) file_get_contents(self::ROTATED_WITH_LOCATION), 'image/jpeg');

        // Turned upright: 20x40, with what was the left half on top
        $size = getimagesizefromstring($jpeg);
        self::assertIsArray($size);
        self::assertSame([20, 40, \IMAGETYPE_JPEG], [$size[0], $size[1], $size[2]]);
        self::assertColor([255, 0, 0], $jpeg, 10, 8);
        self::assertColor([0, 0, 255], $jpeg, 10, 32);

        // No EXIF, so no orientation to turn it again, no location and no device
        self::assertStringNotContainsString('Exif', $jpeg);
        self::assertStringNotContainsString('Acme Phone', $jpeg);
        self::assertStringNotContainsString('ns.adobe.com/xap', $jpeg);
        self::assertStringNotContainsString('GPSLatitude', $jpeg);
        self::assertStringNotContainsString('Taken at home', $jpeg);
        self::assertStringNotContainsString('CREATOR', $jpeg);
    }

    /**
     * An upright JPEG isn't encoded again: its metadata segments are cut out, and its image data is kept byte for byte
     *
     * @test
     */
    public function it_strips_the_metadata_of_an_upright_jpeg_without_encoding_it_again(): void
    {
        $original = (string) file_get_contents(self::ROTATED_WITH_LOCATION);
        // The fixture with orientation 1, which is upright
        $upright = str_replace(pack('nnNn', 0x0112, 3, 1, 6), pack('nnNn', 0x0112, 3, 1, 1), $original);
        self::assertNotSame($original, $upright);

        $jpeg = self::normalizer()->normalize($upright, 'image/jpeg');

        self::assertSame(self::imageData($upright), self::imageData($jpeg));
        $size = getimagesizefromstring($jpeg);
        self::assertIsArray($size);
        self::assertSame([40, 20], [$size[0], $size[1]]);
        self::assertStringNotContainsString('Exif', $jpeg);
        self::assertStringNotContainsString('Acme Phone', $jpeg);
        self::assertStringNotContainsString('ns.adobe.com/xap', $jpeg);
        self::assertStringNotContainsString('Taken at home', $jpeg);
        // The JFIF header stays
        self::assertStringStartsWith("\xFF\xD8\xFF\xE0", $jpeg);
    }

    /**
     * @test
     */
    public function stripping_twice_gives_the_same_file_so_a_stored_original_uploaded_again_is_a_duplicate(): void
    {
        $once = self::normalizer()->normalize((string) file_get_contents(self::ROTATED_WITH_LOCATION), 'image/jpeg');

        self::assertSame($once, self::normalizer()->normalize($once, 'image/jpeg'));
    }

    /**
     * @test
     */
    public function it_strips_the_text_and_exif_chunks_of_a_png(): void
    {
        $png = self::gd(static fn (\GdImage $image): bool => imagepng($image));
        // Before IEND, the last 12 bytes
        $withMetadata = substr($png, 0, -12)
            . self::pngChunk('tEXt', "Author\0Jane Doe")
            . self::pngChunk('iTXt', "Location\0\0\0\0\0Home")
            . self::pngChunk('eXIf', "MM\0*" . pack('N', 8) . pack('n', 0) . pack('N', 0))
            . substr($png, -12);

        $stripped = self::normalizer()->normalize($withMetadata, 'image/png');

        self::assertSame($png, $stripped);
    }

    /**
     * @test
     */
    public function it_strips_the_exif_and_xmp_chunks_of_a_webp_and_clears_their_flags(): void
    {
        $webp = self::gd(static fn (\GdImage $image): bool => imagewebp($image));
        $bitstream = substr($webp, 12);
        // An extended WebP: VP8X, with the EXIF (0x08) and XMP (0x04) flags set, then the image, then the metadata
        $vp8x = self::riffChunk('VP8X', chr(0x08 | 0x04) . "\0\0\0" . substr(pack('V', 40 - 1), 0, 3) . substr(pack('V', 20 - 1), 0, 3));
        $exif = self::riffChunk('EXIF', "MM\0*" . pack('N', 8) . pack('n', 0) . pack('N', 0) . 'Acme Phone');
        $xmp = self::riffChunk('XMP ', '<x:xmpmeta xmlns:x="adobe:ns:meta/">Home</x:xmpmeta>');
        $withMetadata = self::riff($vp8x . $bitstream . $exif . $xmp);

        $stripped = self::normalizer()->normalize($withMetadata, 'image/webp');

        self::assertSame(self::riff(self::riffChunk('VP8X', "\0\0\0\0" . substr(pack('V', 40 - 1), 0, 3) . substr(pack('V', 20 - 1), 0, 3)) . $bitstream), $stripped);
        $size = getimagesizefromstring($stripped);
        self::assertIsArray($size);
        self::assertSame([40, 20], [$size[0], $size[1]]);
    }

    /**
     * @test
     */
    public function it_strips_the_comments_of_a_gif_and_keeps_its_animation_loop(): void
    {
        $gif = self::gd(static fn (\GdImage $image): bool => imagegif($image));
        $loop = "\x21\xFF\x0BNETSCAPE2.0\x03\x01\x00\x00\x00";
        $comment = "\x21\xFE\x0DTaken at home\x00";
        $xmp = "\x21\xFF\x0BXMP DataXMP\x05<x:x>\x00";
        // Before the trailer
        $withMetadata = substr($gif, 0, -1) . $loop . $comment . $xmp . "\x3B";

        $stripped = self::normalizer()->normalize($withMetadata, 'image/gif');

        self::assertSame(substr($gif, 0, -1) . $loop . "\x3B", $stripped);
    }

    /**
     * @test
     */
    public function it_leaves_the_other_kinds_of_files_as_they_are(): void
    {
        self::assertSame('%PDF-1.4', self::normalizer()->normalize('%PDF-1.4', 'application/pdf'));
    }

    /**
     * @test
     *
     * @dataProvider brokenImages
     */
    public function it_refuses_an_image_it_cannot_read(string $content, string $mimeType): void
    {
        $this->expectException(UnreadableFileException::class);

        self::normalizer()->normalize($content, $mimeType);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function brokenImages(): iterable
    {
        yield 'a JPEG cut short' => ["\xFF\xD8\xFF\xE1\x00\x40Exif", 'image/jpeg'];
        yield 'a JPEG without its start' => ['not a jpeg at all', 'image/jpeg'];
        yield 'a PNG without its signature' => ['not a png', 'image/png'];
        yield 'a PNG chunk longer than the file' => ["\x89PNG\r\n\x1A\n" . pack('N', 1000) . 'IHDR', 'image/png'];
        yield 'a WebP without its RIFF header' => ['not a webp', 'image/webp'];
        yield 'a GIF without its header' => ['not a gif', 'image/gif'];
    }

    private static function normalizer(): ImageNormalizer
    {
        return new ImageNormalizer(new Imagine());
    }

    /**
     * @param array{int, int, int} $expected
     */
    private static function assertColor(array $expected, string $image, int $x, int $y): void
    {
        $gd = imagecreatefromstring($image);
        self::assertInstanceOf(\GdImage::class, $gd);
        $rgb = imagecolorat($gd, $x, $y);
        $actual = [($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF];

        // JPEG is lossy
        foreach ($expected as $i => $channel) {
            self::assertEqualsWithDelta($channel, $actual[$i], 40, sprintf('The pixel at %d,%d is rgb(%s)', $x, $y, implode(', ', $actual)));
        }
    }

    /**
     * From the start of scan to the end
     */
    private static function imageData(string $jpeg): string
    {
        $start = strpos($jpeg, "\xFF\xDA");
        self::assertIsInt($start);

        return substr($jpeg, $start);
    }

    /**
     * @param callable(\GdImage): bool $encode
     */
    private static function gd(callable $encode): string
    {
        $image = imagecreatetruecolor(40, 20);
        imagefilledrectangle($image, 0, 0, 19, 19, (int) imagecolorallocate($image, 255, 0, 0));
        ob_start();
        $encode($image);

        return (string) ob_get_clean();
    }

    private static function pngChunk(string $type, string $data): string
    {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    }

    private static function riffChunk(string $fourCc, string $data): string
    {
        return $fourCc . pack('V', strlen($data)) . $data . (1 === strlen($data) % 2 ? "\0" : '');
    }

    private static function riff(string $chunks): string
    {
        return 'RIFF' . pack('V', 4 + strlen($chunks)) . 'WEBP' . $chunks;
    }
}
