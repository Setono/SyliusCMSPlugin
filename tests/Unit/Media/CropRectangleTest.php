<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Media;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Media\CropRectangle;
use Setono\SyliusCMSPlugin\Media\FocalPoint;

final class CropRectangleTest extends TestCase
{
    /**
     * @test
     */
    public function it_keeps_the_whole_height_of_a_wider_image_and_centres_its_width_on_the_focal_point(): void
    {
        $crop = CropRectangle::around(1000, 500, 1.0, new FocalPoint(0.4, 0.5));

        self::assertEquals(new CropRectangle(150, 0, 500, 500), $crop);
    }

    /**
     * @test
     */
    public function it_keeps_the_whole_width_of_a_taller_image_and_centres_its_height_on_the_focal_point(): void
    {
        $crop = CropRectangle::around(600, 1200, 16 / 9, new FocalPoint(0.5, 0.25));

        self::assertEquals(new CropRectangle(0, 131, 600, 338), $crop);
    }

    /**
     * @test
     */
    public function it_moves_the_crop_inside_the_image_when_the_focal_point_is_near_an_edge(): void
    {
        self::assertEquals(new CropRectangle(500, 0, 500, 500), CropRectangle::around(1000, 500, 1.0, new FocalPoint(0.9, 0.5)));
        self::assertEquals(new CropRectangle(0, 0, 500, 500), CropRectangle::around(1000, 500, 1.0, new FocalPoint(0.1, 0.5)));
        self::assertEquals(new CropRectangle(0, 862, 600, 338), CropRectangle::around(600, 1200, 16 / 9, new FocalPoint(0.5, 1.0)));
        self::assertEquals(new CropRectangle(0, 0, 600, 338), CropRectangle::around(600, 1200, 16 / 9, new FocalPoint(0.5, 0.0)));
    }

    /**
     * @test
     */
    public function it_keeps_the_whole_image_when_the_aspect_ratio_is_the_image_s_own(): void
    {
        self::assertEquals(new CropRectangle(0, 0, 1600, 900), CropRectangle::around(1600, 900, 16 / 9, new FocalPoint(0.2, 0.8)));
    }

    /**
     * @test
     */
    public function it_never_crops_to_nothing(): void
    {
        self::assertEquals(new CropRectangle(0, 0, 1, 1000), CropRectangle::around(1, 1000, 0.0001, new FocalPoint()));
        self::assertEquals(new CropRectangle(0, 1, 1000, 1), CropRectangle::around(1000, 3, 5000.0, new FocalPoint()));
    }

    /**
     * @test
     */
    public function it_refuses_an_aspect_ratio_that_is_not_positive(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CropRectangle::around(1000, 500, 0.0, new FocalPoint());
    }
}
