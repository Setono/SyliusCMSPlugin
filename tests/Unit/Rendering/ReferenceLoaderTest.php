<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Rendering;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Rendering\ReferenceLoader;
use Setono\SyliusCMSPlugin\Rendering\ReferenceTypeLoaderInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class ReferenceLoaderTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_loads_the_references_of_each_type_together_each_id_once(): void
    {
        $context = $this->renderContext();
        $hero = new \stdClass();
        $logo = new \stdClass();
        $shirt = new \stdClass();

        $mediaItems = $this->prophesize(ReferenceTypeLoaderInterface::class);
        $mediaItems->load(['hero', 'logo'], $context)->willReturn(['hero' => $hero, 'logo' => $logo])->shouldBeCalledOnce();

        $products = $this->prophesize(ReferenceTypeLoaderInterface::class);
        $products->load(['t_shirt'], $context)->willReturn(['t_shirt' => $shirt])->shouldBeCalledOnce();

        $map = (new ReferenceLoader(new ServiceLocator([
            'media_item' => static fn (): ReferenceTypeLoaderInterface => $mediaItems->reveal(),
            'product' => static fn (): ReferenceTypeLoaderInterface => $products->reveal(),
        ])))->load(self::generate([
            new Reference('media_item', 'hero', true),
            new Reference('product', 't_shirt', false),
            new Reference('media_item', 'logo', true),
            new Reference('media_item', 'hero', true),
        ]), $context);

        self::assertSame($hero, $map->get('media_item', 'hero'));
        self::assertSame($logo, $map->get('media_item', 'logo'));
        self::assertSame($shirt, $map->get('product', 't_shirt'));
    }

    /**
     * @test
     */
    public function it_leaves_out_what_its_type_does_not_resolve(): void
    {
        $context = $this->renderContext();

        $mediaItems = $this->prophesize(ReferenceTypeLoaderInterface::class);
        $mediaItems->load(['hero', 'deleted'], $context)->willReturn(['hero' => new \stdClass()]);

        $map = (new ReferenceLoader(new ServiceLocator(['media_item' => static fn (): ReferenceTypeLoaderInterface => $mediaItems->reveal()])))
            ->load([new Reference('media_item', 'hero', true), new Reference('media_item', 'deleted', true)], $context);

        self::assertTrue($map->has('media_item', 'hero'));
        self::assertFalse($map->has('media_item', 'deleted'));
        self::assertNull($map->get('media_item', 'deleted'));
    }

    /**
     * @test
     */
    public function it_leaves_types_that_load_nothing_out(): void
    {
        $map = (new ReferenceLoader(new ServiceLocator([])))->load([new Reference('customer_group', 'wholesale', false)], $this->renderContext());

        self::assertFalse($map->has('customer_group', 'wholesale'));
    }

    /**
     * @test
     */
    public function it_loads_nothing_without_references(): void
    {
        $mediaItems = $this->prophesize(ReferenceTypeLoaderInterface::class);
        $mediaItems->load(Argument::cetera())->shouldNotBeCalled();

        $map = (new ReferenceLoader(new ServiceLocator(['media_item' => static fn (): ReferenceTypeLoaderInterface => $mediaItems->reveal()])))
            ->load([], $this->renderContext());

        self::assertSame([], $map->all('media_item'));
    }

    /**
     * @param list<Reference> $references
     *
     * @return \Generator<Reference>
     */
    private static function generate(array $references): \Generator
    {
        yield from $references;
    }

    private function renderContext(): RenderContext
    {
        return new RenderContext($this->prophesize(ChannelInterface::class)->reveal(), 'en_US', 'USD', null, '@guest', new \DateTimeImmutable());
    }
}
