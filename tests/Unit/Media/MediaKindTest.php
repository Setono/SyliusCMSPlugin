<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Media;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Media\MediaKind;

final class MediaKindTest extends TestCase
{
    /**
     * @test
     */
    public function it_gives_documents_and_videos_a_permanent_url_and_images_none(): void
    {
        self::assertTrue(MediaKind::Document->hasPermanentUrl());
        self::assertTrue(MediaKind::Video->hasPermanentUrl());
        self::assertFalse(MediaKind::Image->hasPermanentUrl());
        self::assertSame([MediaKind::Video, MediaKind::Document], MediaKind::withPermanentUrl());
    }
}
