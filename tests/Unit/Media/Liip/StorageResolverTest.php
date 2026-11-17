<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Media\Liip;

use League\Flysystem\FilesystemOperator;
use Liip\ImagineBundle\Imagine\Filter\FilterConfiguration;
use Liip\ImagineBundle\Model\Binary;
use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Media\Liip\FilterSets;
use Setono\SyliusCMSPlugin\Media\Liip\StorageResolver;
use Setono\SyliusCMSPlugin\Media\Liip\VariantPaths;
use Setono\SyliusCMSPlugin\Media\MediaUrlGenerator;
use Setono\SyliusCMSPlugin\Tests\Support\StorageDirectory;

final class StorageResolverTest extends TestCase
{
    use StorageDirectory;

    private const SOURCE = '0196b2a4-7c1e-7f3a-9d2b-1a2b3c4d5e6f/9f3c2a1b4d5e6f70/summer-sale.jpg';

    private const OTHER_SOURCE = '0196b2a4-7c1e-7f3a-9d2b-1a2b3c4d5e6f/0a1b2c3d4e5f6071/summer-sale.jpg';

    private FilesystemOperator $storage;

    private StorageResolver $resolver;

    protected function setUp(): void
    {
        $this->storage = $this->storage();
        $this->resolver = new StorageResolver($this->storage, new VariantPaths(new FilterConfiguration(FilterSets::config('setono_sylius_cms', 'setono_sylius_cms'))), new MediaUrlGenerator());
    }

    /**
     * @test
     */
    public function it_caches_a_variant_in_the_storage_under_its_source_with_the_extension_of_its_format_and_resolves_to_its_public_url(): void
    {
        self::assertFalse($this->resolver->isStored('rc/Ab3dE6gH/' . self::SOURCE, 'setono_sylius_cms_webp'));

        $this->resolver->store(new Binary('webp bytes', 'image/webp', 'webp'), 'rc/Ab3dE6gH/' . self::SOURCE, 'setono_sylius_cms_webp');

        self::assertTrue($this->resolver->isStored('rc/Ab3dE6gH/' . self::SOURCE, 'setono_sylius_cms_webp'));
        self::assertSame('webp bytes', $this->storage->read('cache/setono_sylius_cms_webp/' . self::SOURCE . '/Ab3dE6gH/summer-sale.webp'));
        self::assertSame('/media/cms/cache/setono_sylius_cms_webp/' . self::SOURCE . '/Ab3dE6gH/summer-sale.webp', $this->resolver->resolve('rc/Ab3dE6gH/' . self::SOURCE, 'setono_sylius_cms_webp'));
        self::assertFalse($this->resolver->isStored('rc/Zz9yX8wV/' . self::SOURCE, 'setono_sylius_cms_webp'), 'Another variant of the source isn\'t stored');
    }

    /**
     * @test
     */
    public function it_keeps_the_source_s_extension_for_a_variant_in_the_source_s_format_and_caches_a_variant_without_runtime_filters_beside_the_others(): void
    {
        $this->resolver->store(new Binary('jpeg bytes', 'image/jpeg', 'jpg'), 'rc/Ab3dE6gH/' . self::SOURCE, 'setono_sylius_cms_original');
        $this->resolver->store(new Binary('avif bytes', 'image/avif', 'avif'), self::SOURCE, 'setono_sylius_cms_avif');

        self::assertSame('jpeg bytes', $this->storage->read('cache/setono_sylius_cms_original/' . self::SOURCE . '/Ab3dE6gH/summer-sale.jpg'));
        self::assertSame('avif bytes', $this->storage->read('cache/setono_sylius_cms_avif/' . self::SOURCE . '/summer-sale.avif'));
    }

    /**
     * @test
     */
    public function it_removes_every_variant_of_a_source_in_the_filter_sets_and_leaves_the_others(): void
    {
        $this->resolver->store(new Binary('a', 'image/webp', 'webp'), 'rc/Ab3dE6gH/' . self::SOURCE, 'setono_sylius_cms_webp');
        $this->resolver->store(new Binary('b', 'image/webp', 'webp'), 'rc/Zz9yX8wV/' . self::SOURCE, 'setono_sylius_cms_webp');
        $this->resolver->store(new Binary('c', 'image/avif', 'avif'), 'rc/Ab3dE6gH/' . self::SOURCE, 'setono_sylius_cms_avif');
        $this->resolver->store(new Binary('d', 'image/webp', 'webp'), 'rc/Ab3dE6gH/' . self::OTHER_SOURCE, 'setono_sylius_cms_webp');

        $this->resolver->remove([self::SOURCE], ['setono_sylius_cms_webp', 'setono_sylius_cms_avif']);

        self::assertFalse($this->resolver->isStored('rc/Ab3dE6gH/' . self::SOURCE, 'setono_sylius_cms_webp'));
        self::assertFalse($this->resolver->isStored('rc/Zz9yX8wV/' . self::SOURCE, 'setono_sylius_cms_webp'));
        self::assertFalse($this->resolver->isStored('rc/Ab3dE6gH/' . self::SOURCE, 'setono_sylius_cms_avif'));
        self::assertTrue($this->resolver->isStored('rc/Ab3dE6gH/' . self::OTHER_SOURCE, 'setono_sylius_cms_webp'));
    }

    /**
     * @test
     */
    public function it_removes_one_variant_given_its_runtime_path(): void
    {
        $this->resolver->store(new Binary('a', 'image/webp', 'webp'), 'rc/Ab3dE6gH/' . self::SOURCE, 'setono_sylius_cms_webp');
        $this->resolver->store(new Binary('b', 'image/webp', 'webp'), 'rc/Zz9yX8wV/' . self::SOURCE, 'setono_sylius_cms_webp');

        $this->resolver->remove(['rc/Ab3dE6gH/' . self::SOURCE], ['setono_sylius_cms_webp']);

        self::assertFalse($this->resolver->isStored('rc/Ab3dE6gH/' . self::SOURCE, 'setono_sylius_cms_webp'));
        self::assertTrue($this->resolver->isStored('rc/Zz9yX8wV/' . self::SOURCE, 'setono_sylius_cms_webp'));
    }

    /**
     * @test
     */
    public function it_removes_every_variant_of_a_filter_set_given_no_paths(): void
    {
        $this->resolver->store(new Binary('a', 'image/webp', 'webp'), 'rc/Ab3dE6gH/' . self::SOURCE, 'setono_sylius_cms_webp');
        $this->resolver->store(new Binary('b', 'image/webp', 'webp'), 'rc/Ab3dE6gH/' . self::OTHER_SOURCE, 'setono_sylius_cms_webp');
        $this->resolver->store(new Binary('c', 'image/avif', 'avif'), 'rc/Ab3dE6gH/' . self::SOURCE, 'setono_sylius_cms_avif');

        $this->resolver->remove([], ['setono_sylius_cms_webp']);

        self::assertFalse($this->resolver->isStored('rc/Ab3dE6gH/' . self::SOURCE, 'setono_sylius_cms_webp'));
        self::assertFalse($this->resolver->isStored('rc/Ab3dE6gH/' . self::OTHER_SOURCE, 'setono_sylius_cms_webp'));
        self::assertTrue($this->resolver->isStored('rc/Ab3dE6gH/' . self::SOURCE, 'setono_sylius_cms_avif'));
    }
}
