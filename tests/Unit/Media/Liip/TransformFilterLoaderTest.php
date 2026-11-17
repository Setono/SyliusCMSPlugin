<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Media\Liip;

use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\Palette\RGB;
use Imagine\Image\Point;
use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Media\Liip\TransformFilterLoader;

final class TransformFilterLoaderTest extends TestCase
{
    /**
     * @test
     */
    public function it_crops_to_the_aspect_ratio_around_the_focal_point_and_scales_down_to_the_width(): void
    {
        // Options arrive from the variant's URL as strings
        $right = (new TransformFilterLoader())->load(self::redLeftBlueRight(), ['width' => '100', 'height' => '100', 'x' => '0.9', 'y' => '0.5']);
        $left = (new TransformFilterLoader())->load(self::redLeftBlueRight(), ['width' => '100', 'height' => '100', 'x' => '0.1', 'y' => '0.5']);

        self::assertEquals(new Box(100, 100), $right->getSize());
        self::assertSame('#0000ff', (string) $right->getColorAt(new Point(50, 50)));
        self::assertEquals(new Box(100, 100), $left->getSize());
        self::assertSame('#ff0000', (string) $left->getColorAt(new Point(50, 50)));
    }

    /**
     * @test
     */
    public function it_scales_an_image_down_to_the_width_keeping_its_aspect_ratio_without_a_height(): void
    {
        $image = (new TransformFilterLoader())->load(self::redLeftBlueRight(), ['width' => '100', 'x' => '0.5', 'y' => '0.5']);

        self::assertEquals(new Box(100, 50), $image->getSize());
    }

    /**
     * @test
     */
    public function it_never_scales_an_image_up(): void
    {
        self::assertEquals(new Box(400, 200), (new TransformFilterLoader())->load(self::redLeftBlueRight(), ['width' => '800', 'x' => '0.5', 'y' => '0.5'])->getSize());
        self::assertEquals(new Box(200, 200), (new TransformFilterLoader())->load(self::redLeftBlueRight(), ['width' => '300', 'height' => '300', 'x' => '0.5', 'y' => '0.5'])->getSize());
    }

    /**
     * @test
     */
    public function it_crops_around_the_centre_without_a_focal_point(): void
    {
        $image = (new TransformFilterLoader())->load(self::redLeftBlueRight(), ['width' => '100', 'height' => '200']);

        self::assertEquals(new Box(100, 200), $image->getSize());
        self::assertSame('#ff0000', (string) $image->getColorAt(new Point(10, 100)));
        self::assertSame('#0000ff', (string) $image->getColorAt(new Point(90, 100)));
    }

    /**
     * @test
     */
    public function it_refuses_a_width_that_is_not_a_positive_number_of_pixels(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new TransformFilterLoader())->load(self::redLeftBlueRight(), ['width' => '0']);
    }

    /**
     * @test
     */
    public function it_refuses_a_focal_point_outside_the_image(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new TransformFilterLoader())->load(self::redLeftBlueRight(), ['width' => '100', 'x' => '1.5', 'y' => '0.5']);
    }

    /**
     * 400 by 200 pixels, red on the left half and blue on the right
     */
    private static function redLeftBlueRight(): ImageInterface
    {
        $palette = new RGB();
        $image = (new Imagine())->create(new Box(400, 200), $palette->color('#ff0000'));
        $image->draw()->rectangle(new Point(200, 0), new Point(399, 199), $palette->color('#0000ff'), true);

        return $image;
    }
}
