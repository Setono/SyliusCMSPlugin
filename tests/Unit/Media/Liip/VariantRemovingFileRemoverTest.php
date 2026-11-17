<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Media\Liip;

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Imagine\Cache\Resolver\ResolverInterface;
use Liip\ImagineBundle\Imagine\Cache\Signer;
use Liip\ImagineBundle\Imagine\Filter\FilterConfiguration;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Media\Liip\FilterSets;
use Setono\SyliusCMSPlugin\Media\Liip\VariantRemovingFileRemover;
use Setono\SyliusCMSPlugin\Media\MediaFileRemoverInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Routing\RouterInterface;

final class VariantRemovingFileRemoverTest extends TestCase
{
    use ProphecyTrait;

    private const PATH = '0196b2a4-7c1e-7f3a-9d2b-1a2b3c4d5e6f/9f3c2a1b4d5e6f70/summer-sale.jpg';

    /**
     * @test
     */
    public function it_removes_the_file_and_then_every_variant_made_from_it_in_each_format(): void
    {
        $removed = [];
        $files = $this->prophesize(MediaFileRemoverInterface::class);
        $files->remove(self::PATH)->will(static function () use (&$removed): void {
            $removed[] = 'file';
        })->shouldBeCalledOnce();

        $resolver = $this->prophesize(ResolverInterface::class);
        $resolver->remove([self::PATH], ['setono_sylius_cms_webp', 'setono_sylius_cms_avif', 'setono_sylius_cms_original'])->will(static function () use (&$removed): void {
            $removed[] = 'variants';
        })->shouldBeCalledOnce();
        $resolver->remove(Argument::cetera())->shouldBeCalledOnce();

        $cacheManager = new CacheManager(new FilterConfiguration(FilterSets::config('setono_sylius_cms', 'setono_sylius_cms')), $this->prophesize(RouterInterface::class)->reveal(), new Signer('secret'), new EventDispatcher());
        $cacheManager->addResolver('setono_sylius_cms', $resolver->reveal());

        (new VariantRemovingFileRemover($files->reveal(), $cacheManager))->remove(self::PATH);

        self::assertSame(['file', 'variants'], $removed, 'No variant is made again from the file between the two');
    }
}
