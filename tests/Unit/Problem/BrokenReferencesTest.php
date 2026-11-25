<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Problem;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Model\MediaItem;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Problem\BrokenReferences;
use Setono\SyliusCMSPlugin\Rendering\ReferenceTypeLoaderInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\Product;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Content refers to Sylius's records by their codes and to the CMS's by their UUIDs (ADR 0002). See "References" in
 * docs/spec/content-model.md
 */
final class BrokenReferencesTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_gives_the_references_whose_records_the_store_no_longer_has_with_their_keys_one_query_per_type(): void
    {
        $products = $this->prophesize(ReferenceTypeLoaderInterface::class);
        $products->existing(['MUG', 'CAP'])->willReturn(['MUG'])->shouldBeCalledOnce();
        $mediaItems = $this->prophesize(ReferenceTypeLoaderInterface::class);
        $mediaItems->existing(['0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4a'])->willReturn([])->shouldBeCalledOnce();
        $mug = new Reference('product', 'MUG', false);
        $cap = new Reference('product', 'CAP', false);
        $capAgain = new Reference('product', 'CAP', false);
        $image = new Reference('media_item', '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4a', true);
        // Of a type without a loader, so it isn't checked
        $channel = new Reference('channel', 'OUTLET', false);

        $broken = $this->references($products->reveal(), $mediaItems->reveal())->broken([3 => $mug, 5 => $cap, 6 => $image, 7 => $channel, 9 => $capAgain]);

        self::assertSame([5 => $cap, 6 => $image, 9 => $capAgain], $broken);
    }

    /**
     * @test
     */
    public function it_finds_the_entries_that_may_refer_to_the_records_by_their_code_or_uuid_now_or_before(): void
    {
        $product = new Product();
        $product->setCode('MUG');
        $channel = new Channel();
        $channel->setCode('OUTLET');
        $mediaItem = new MediaItem();
        $page = new Page();
        $entries = $this->prophesize(EntryRepositoryInterface::class);
        $entries->findReferencing(['OLD', 'MUG', 'OUTLET', $mediaItem->getUuid(), $page->getUuid()])->willReturn([$page])->shouldBeCalledOnce();

        self::assertSame([$page], [...$this->references(entries: $entries->reveal())->entriesReferencing([$product, $channel, $mediaItem, $page, $product, new \stdClass()], ['OLD'])]);
    }

    /**
     * @test
     */
    public function it_looks_nothing_up_for_records_without_a_code_or_uuid(): void
    {
        $entries = $this->prophesize(EntryRepositoryInterface::class);
        $entries->findReferencing(Argument::any())->shouldNotBeCalled();

        self::assertSame([], [...$this->references(entries: $entries->reveal())->entriesReferencing([new Channel(), new \stdClass()])]);
    }

    private function references(
        ?ReferenceTypeLoaderInterface $products = null,
        ?ReferenceTypeLoaderInterface $mediaItems = null,
        ?EntryRepositoryInterface $entries = null,
    ): BrokenReferences {
        $loaders = [];
        if (null !== $products) {
            $loaders['product'] = static fn (): ReferenceTypeLoaderInterface => $products;
        }

        if (null !== $mediaItems) {
            $loaders['media_item'] = static fn (): ReferenceTypeLoaderInterface => $mediaItems;
        }

        return new BrokenReferences(new ServiceLocator($loaders), $entries ?? $this->prophesize(EntryRepositoryInterface::class)->reveal());
    }
}
