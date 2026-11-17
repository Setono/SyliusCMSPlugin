<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Media;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Media\MediaUrlGenerator;

final class MediaUrlGeneratorTest extends TestCase
{
    /**
     * @test
     */
    public function it_builds_a_file_s_url_under_the_public_directory_the_default_storage_writes_to(): void
    {
        self::assertSame(
            '/media/cms/0196b2a4-7c1e-7f3a-9d2b-1a2b3c4d5e6f/9f3c2a1b4d5e6f70/summer-sale.jpg',
            (new MediaUrlGenerator())->generate('0196b2a4-7c1e-7f3a-9d2b-1a2b3c4d5e6f/9f3c2a1b4d5e6f70/summer-sale.jpg'),
        );
    }

    /**
     * @test
     */
    public function it_builds_a_cached_variant_s_url_the_same_way(): void
    {
        self::assertSame(
            '/media/cms/cache/setono_sylius_cms_webp/0196b2a4-7c1e-7f3a-9d2b-1a2b3c4d5e6f/9f3c2a1b4d5e6f70/summer-sale.jpg/Ab3dE6gH/summer-sale.webp',
            (new MediaUrlGenerator())->generate('/cache/setono_sylius_cms_webp/0196b2a4-7c1e-7f3a-9d2b-1a2b3c4d5e6f/9f3c2a1b4d5e6f70/summer-sale.jpg/Ab3dE6gH/summer-sale.webp'),
        );
    }

    /**
     * @test
     */
    public function it_builds_urls_under_another_base_url(): void
    {
        self::assertSame('https://cdn.example.com/cms/a/b/c.pdf', (new MediaUrlGenerator('https://cdn.example.com/cms/'))->generate('a/b/c.pdf'));
    }
}
