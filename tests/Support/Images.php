<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

/**
 * Image files of a size in one colour, made with GD, for tests that store, check or upload them
 */
final class Images
{
    private function __construct()
    {
    }

    /**
     * @param int<1, max> $width
     * @param int<1, max> $height
     * @param array{int<0, 255>, int<0, 255>, int<0, 255>} $rgb
     */
    public static function png(int $width = 40, int $height = 20, array $rgb = [200, 30, 30]): string
    {
        $image = self::filled($width, $height, $rgb);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    /**
     * @param int<1, max> $width
     * @param int<1, max> $height
     * @param array{int<0, 255>, int<0, 255>, int<0, 255>} $rgb
     */
    public static function jpeg(int $width = 40, int $height = 20, array $rgb = [30, 30, 200]): string
    {
        $image = self::filled($width, $height, $rgb);
        ob_start();
        imagejpeg($image);

        return (string) ob_get_clean();
    }

    /**
     * @param int<1, max> $width
     * @param int<1, max> $height
     * @param array{int<0, 255>, int<0, 255>, int<0, 255>} $rgb
     */
    private static function filled(int $width, int $height, array $rgb): \GdImage
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, ...$rgb));

        return $image;
    }
}
