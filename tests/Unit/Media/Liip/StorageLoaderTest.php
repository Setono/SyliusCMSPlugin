<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Media\Liip;

use Liip\ImagineBundle\Exception\Binary\Loader\NotLoadableException;
use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Media\Liip\StorageLoader;
use Symfony\Component\Mime\MimeTypes;
use Setono\SyliusCMSPlugin\Tests\Support\Images;
use Setono\SyliusCMSPlugin\Tests\Support\StorageDirectory;

final class StorageLoaderTest extends TestCase
{
    use StorageDirectory;

    private const SOURCE = '0196b2a4-7c1e-7f3a-9d2b-1a2b3c4d5e6f/9f3c2a1b4d5e6f70/summer-sale.png';

    /**
     * @test
     */
    public function it_reads_a_source_image_from_the_storage_with_its_mime_type_and_format(): void
    {
        $storage = $this->storage();
        $png = Images::png(4, 2);
        $storage->write(self::SOURCE, $png);

        $binary = (new StorageLoader($storage, new MimeTypes()))->find(self::SOURCE);

        self::assertSame($png, $binary->getContent());
        self::assertSame('image/png', $binary->getMimeType());
        self::assertSame('png', $binary->getFormat());
    }

    /**
     * @test
     */
    public function it_cannot_load_a_file_the_storage_does_not_have(): void
    {
        $this->expectException(NotLoadableException::class);

        (new StorageLoader($this->storage(), new MimeTypes()))->find(self::SOURCE);
    }

    /**
     * @test
     */
    public function it_cannot_load_a_path_outside_the_storage(): void
    {
        $this->expectException(NotLoadableException::class);

        (new StorageLoader($this->storage(), new MimeTypes()))->find('../../etc/passwd');
    }

    /**
     * @test
     */
    public function it_does_not_load_a_cached_variant_as_a_source(): void
    {
        $storage = $this->storage();
        $storage->write('cache/setono_sylius_cms_webp/' . self::SOURCE . '/Ab3dE6gH/summer-sale.png', Images::png(4, 2));

        $this->expectException(NotLoadableException::class);

        (new StorageLoader($storage, new MimeTypes()))->find('cache/setono_sylius_cms_webp/' . self::SOURCE . '/Ab3dE6gH/summer-sale.png');
    }
}
