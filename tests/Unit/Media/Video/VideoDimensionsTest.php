<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Media\Video;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Media\Video\VideoDimensions;
use Setono\SyliusCMSPlugin\Tests\Support\TemporaryDirectory;

/**
 * A video's dimensions are read on upload, from the file itself. See "Metadata" in docs/spec/media.md
 */
final class VideoDimensionsTest extends TestCase
{
    use TemporaryDirectory;

    /** The identity matrix of a track header */
    private const UPRIGHT = [0x00010000, 0, 0, 0, 0x00010000, 0, 0, 0, 0x40000000];

    /** A track header's matrix that turns the picture 90 degrees, as phones record portrait videos */
    private const TURNED = [0, 0x00010000, 0, -0x00010000 & 0xFFFFFFFF, 0, 0, 0, 0, 0x40000000];

    /**
     * @test
     */
    public function it_reads_the_dimensions_of_an_mp4_from_its_video_tracks_header(): void
    {
        $mp4 = self::box('ftyp', 'isom' . pack('N', 512) . 'isomiso2mp41')
            . self::box('mdat', str_repeat("\0", 64))
            // The audio track comes first and has no dimensions; the movie box comes after the media data here
            . self::box('moov', self::box('mvhd', str_repeat("\0", 100)) . self::track(0, 0, self::UPRIGHT) . self::track(1920, 1080, self::UPRIGHT, version: 1));

        self::assertSame([1920, 1080], VideoDimensions::read($this->temporaryFile('film.mp4', $mp4), 'video/mp4'));
    }

    /**
     * @test
     */
    public function it_swaps_the_dimensions_of_an_mp4_that_is_shown_turned(): void
    {
        $mp4 = self::box('ftyp', 'isom' . pack('N', 512) . 'isom') . self::box('moov', self::track(1920, 1080, self::TURNED));

        self::assertSame([1080, 1920], VideoDimensions::read($this->temporaryFile('portrait.mp4', $mp4), 'video/mp4'));
    }

    /**
     * @test
     */
    public function it_reads_the_dimensions_of_a_webm_from_its_video_track(): void
    {
        $webm = self::ebml(0x1A45DFA3, self::ebml(0x4282, 'webm'))
            . self::ebml(0x18538067, self::ebml(0x1549A966, self::ebml(0x2AD7B1, "\x0F\x42\x40"))
                . self::ebml(0x1654AE6B, self::ebml(0xAE, self::ebml(0x83, "\x02")) . self::ebml(0xAE, self::ebml(0x83, "\x01") . self::ebml(0xE0, self::ebml(0xB0, "\x05\x00") . self::ebml(0xBA, "\x02\xD0"))))
                . self::ebml(0x1F43B675, str_repeat("\0", 32)), unknownSize: true);

        self::assertSame([1280, 720], VideoDimensions::read($this->temporaryFile('clip.webm', $webm), 'video/webm'));
    }

    /**
     * @test
     */
    public function it_has_no_dimensions_for_a_video_it_cannot_read(): void
    {
        self::assertNull(VideoDimensions::read($this->temporaryFile('broken.mp4', self::box('ftyp', 'isom') . pack('N', 1000) . 'moov'), 'video/mp4'));
        self::assertNull(VideoDimensions::read($this->temporaryFile('broken.webm', "\x1A\x45\xDF\xA3\x88"), 'video/webm'));
        self::assertNull(VideoDimensions::read($this->temporaryFile('film.ogv', 'OggS'), 'video/ogg'));
    }

    private static function box(string $type, string $data): string
    {
        return pack('N', 8 + strlen($data)) . $type . $data;
    }

    /**
     * @param list<int> $matrix
     */
    private static function track(int $width, int $height, array $matrix, int $version = 0): string
    {
        $times = 0 === $version ? str_repeat("\0", 20) : str_repeat("\0", 32);
        $header = chr($version) . "\0\0\x07" . $times . str_repeat("\0", 16) . pack('N*', ...$matrix) . pack('NN', $width << 16, $height << 16);

        return self::box('trak', self::box('tkhd', $header) . self::box('mdia', str_repeat("\0", 16)));
    }

    private static function ebml(int $id, string $data, bool $unknownSize = false): string
    {
        $idBytes = ltrim(pack('N', $id), "\0");
        // Sizes as 8-byte variable-length integers, and all ones for an unknown size, as live recordings write the segment
        $size = $unknownSize ? "\x01\xFF\xFF\xFF\xFF\xFF\xFF\xFF" : "\x01" . substr(pack('J', strlen($data)), 1);

        return $idBytes . $size . $data;
    }
}
