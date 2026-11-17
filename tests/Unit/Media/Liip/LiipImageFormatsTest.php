<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Media\Liip;

use Imagine\Image\ImagineInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Media\ImageFormat;
use Setono\SyliusCMSPlugin\Media\Liip\LiipImageFormats;

final class LiipImageFormatsTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_serves_webp_and_the_original_format_and_avif_only_where_the_image_library_says_it_writes_it(): void
    {
        $formats = new LiipImageFormats($this->prophesize(ImagineInterface::class)->reveal());

        self::assertTrue($formats->supports(ImageFormat::Webp));
        self::assertTrue($formats->supports(ImageFormat::Original));
        self::assertFalse($formats->supports(ImageFormat::Avif), 'An image library that can\'t say what it supports isn\'t trusted with AVIF');
    }
}
