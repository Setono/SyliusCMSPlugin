<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Context;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Context\ContextResolverInterface;
use Setono\SyliusCMSPlugin\Context\HostPageContextResolver;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Registry\ContextKeyRegistry;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\Taxon;
use Sylius\Component\Core\Model\TaxonInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * A slot takes the keys it provides from what it's passed, and runs the context resolver of each key it isn't passed,
 * with the main request. See "Context resolvers" in docs/spec/slots.md and "Rendering a slot" in docs/spec/rendering.md
 */
final class HostPageContextResolverTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ContextResolverInterface> */
    private ObjectProphecy $productResolver;

    /** @var ObjectProphecy<ContextResolverInterface> */
    private ObjectProphecy $taxonResolver;

    private RequestStack $requestStack;

    private Request $mainRequest;

    protected function setUp(): void
    {
        $this->productResolver = $this->prophesize(ContextResolverInterface::class);
        $this->taxonResolver = $this->prophesize(ContextResolverInterface::class);
        $this->mainRequest = Request::create('/en_US/products/t-shirt');
        $this->requestStack = new RequestStack();
        $this->requestStack->push($this->mainRequest);
    }

    /**
     * @test
     */
    public function it_takes_the_keys_it_is_passed_without_running_their_resolvers(): void
    {
        $product = new Product();
        $this->productResolver->resolve(Argument::any())->shouldNotBeCalled();

        self::assertSame(['product' => $product], $this->resolver()->resolve(['product'], ['product' => $product, 'taxon' => new Taxon(), 'title' => 'Not a key']));
    }

    /**
     * @test
     */
    public function it_runs_the_resolver_of_each_key_it_is_not_passed_with_the_main_request(): void
    {
        $product = new Product();
        $taxon = new Taxon();
        $this->productResolver->resolve($this->mainRequest)->willReturn($product);
        $this->taxonResolver->resolve(Argument::any())->shouldNotBeCalled();

        self::assertSame(['product' => $product, 'taxon' => $taxon], $this->resolver()->resolve(['product', 'taxon'], ['taxon' => $taxon]));
    }

    /**
     * Sylius's taxon header renders in a sub-request, whose route isn't the page's
     *
     * @test
     */
    public function it_runs_a_resolver_with_the_main_request_inside_a_sub_request(): void
    {
        $taxon = new Taxon();
        $this->requestStack->push(Request::create('/_partial/taxons/by-slug/t-shirts'));
        $this->taxonResolver->resolve($this->mainRequest)->willReturn($taxon)->shouldBeCalledOnce();

        self::assertSame(['taxon' => $taxon], $this->resolver()->resolve(['taxon'], []));
    }

    /**
     * @test
     */
    public function it_runs_the_resolver_of_a_key_it_is_passed_something_else_for(): void
    {
        $product = new Product();
        $this->productResolver->resolve($this->mainRequest)->willReturn($product);

        self::assertSame(['product' => $product], $this->resolver()->resolve(['product'], ['product' => new Taxon()]));
    }

    /**
     * @test
     */
    public function it_resolves_nothing_when_a_key_s_resolver_finds_nothing(): void
    {
        $this->productResolver->resolve($this->mainRequest)->willReturn(null);

        self::assertNull($this->resolver()->resolve(['product', 'taxon'], ['taxon' => new Taxon()]));
    }

    /**
     * @test
     */
    public function it_resolves_nothing_for_a_key_it_is_not_passed_that_has_no_resolver(): void
    {
        self::assertNull($this->resolver()->resolve(['entry'], []));
        self::assertSame([], $this->resolver()->resolve([], []));
    }

    /**
     * @test
     */
    public function it_resolves_nothing_without_a_main_request(): void
    {
        $this->productResolver->resolve(Argument::any())->shouldNotBeCalled();

        self::assertNull($this->resolver(new RequestStack())->resolve(['product'], []));
    }

    /**
     * Every slot on a page that resolves a key reuses what its resolver found, until the next main request
     *
     * @test
     */
    public function it_runs_each_resolver_once_a_main_request(): void
    {
        $product = new Product();
        $this->productResolver->resolve(Argument::type(Request::class))->willReturn($product)->shouldBeCalledTimes(2);
        $this->taxonResolver->resolve($this->mainRequest)->willReturn(null)->shouldBeCalledOnce();
        $resolver = $this->resolver();

        self::assertSame(['product' => $product], $resolver->resolve(['product'], []));
        self::assertSame(['product' => $product], $resolver->resolve(['product'], []));
        self::assertNull($resolver->resolve(['taxon'], []));
        self::assertNull($resolver->resolve(['taxon'], []));

        $this->requestStack->pop();
        $this->requestStack->push(Request::create('/en_US/products/mug'));

        self::assertSame(['product' => $product], $resolver->resolve(['product'], []));
    }

    /**
     * @test
     */
    public function it_throws_when_a_resolver_finds_an_object_its_key_does_not_hold(): void
    {
        $this->productResolver->resolve($this->mainRequest)->willReturn(new Taxon());

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage(sprintf('The context resolver of the key "product" must find a %s or null, but found a %s', ProductInterface::class, Taxon::class));

        $this->resolver()->resolve(['product'], []);
    }

    private function resolver(?RequestStack $requestStack = null): HostPageContextResolver
    {
        return new HostPageContextResolver(
            new ContextKeyRegistry([
                'product' => ['class' => ProductInterface::class, 'label' => 'setono_sylius_cms.context_key.product'],
                'taxon' => ['class' => TaxonInterface::class, 'label' => 'setono_sylius_cms.context_key.taxon'],
                'entry' => ['class' => EntryInterface::class, 'label' => 'setono_sylius_cms.context_key.entry'],
            ]),
            new ServiceLocator([
                'product' => $this->productResolver->reveal(...),
                'taxon' => $this->taxonResolver->reveal(...),
            ]),
            $requestStack ?? $this->requestStack,
        );
    }
}
