<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Profiler;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Context\HostPageContextResolverInterface;
use Setono\SyliusCMSPlugin\Profiler\RenderedSlots;
use Setono\SyliusCMSPlugin\Profiler\TraceableHostPageContextResolver;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\Taxon;

final class TraceableHostPageContextResolverTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_records_the_context_a_slot_resolved_and_whether_each_object_was_passed_or_found_by_its_resolver(): void
    {
        $product = new Product();
        $taxon = new Taxon();
        $passed = ['product' => $product, 'sylius' => 'the Twig global'];
        $resolver = $this->prophesize(HostPageContextResolverInterface::class);
        $resolver->resolve(['product', 'taxon'], $passed)->willReturn(['product' => $product, 'taxon' => $taxon]);
        $slots = new RenderedSlots();
        $slot = $slots->start('app.product_in_taxon');

        $context = (new TraceableHostPageContextResolver($resolver->reveal(), $slots))->resolve(['product', 'taxon'], $passed);

        self::assertSame(['product' => $product, 'taxon' => $taxon], $context);
        self::assertSame(['product' => ['object' => $product, 'passed' => true], 'taxon' => ['object' => $taxon, 'passed' => false]], $slot->context);
        self::assertNull($slot->unresolvedKey);
    }

    /**
     * A taxon passed under the key product isn't the product, so the resolver found the one the slot got
     *
     * @test
     */
    public function it_records_an_object_the_slot_was_passed_but_did_not_get_as_found_by_its_resolver(): void
    {
        $product = new Product();
        $passed = ['product' => new Taxon()];
        $resolver = $this->prophesize(HostPageContextResolverInterface::class);
        $resolver->resolve(['product'], $passed)->willReturn(['product' => $product]);
        $slots = new RenderedSlots();
        $slot = $slots->start('product.top');

        (new TraceableHostPageContextResolver($resolver->reveal(), $slots))->resolve(['product'], $passed);

        self::assertSame(['product' => ['object' => $product, 'passed' => false]], $slot->context);
    }

    /**
     * The resolver stops at the first key it can't resolve, having asked that key's resolver, whose answer it reuses
     *
     * @test
     */
    public function it_records_the_first_key_a_slot_could_not_resolve(): void
    {
        $product = new Product();
        $resolver = $this->prophesize(HostPageContextResolverInterface::class);
        $resolver->resolve(['product', 'taxon', 'entry'], [])->willReturn(null);
        $resolver->resolve(['product'], [])->willReturn(['product' => $product]);
        $resolver->resolve(['taxon'], [])->willReturn(null);
        $resolver->resolve(['entry'], [])->shouldNotBeCalled();
        $slots = new RenderedSlots();
        $slot = $slots->start('app.everything');

        self::assertNull((new TraceableHostPageContextResolver($resolver->reveal(), $slots))->resolve(['product', 'taxon', 'entry'], []));

        self::assertSame('taxon', $slot->unresolvedKey);
        self::assertNull($slot->context);
    }

    /**
     * @test
     */
    public function it_records_nothing_outside_a_slot(): void
    {
        $resolver = $this->prophesize(HostPageContextResolverInterface::class);
        $resolver->resolve(['product'], [])->willReturn(null)->shouldBeCalledOnce();
        $slots = new RenderedSlots();

        self::assertNull((new TraceableHostPageContextResolver($resolver->reveal(), $slots))->resolve(['product'], []));
        self::assertSame([], $slots->all());
    }
}
