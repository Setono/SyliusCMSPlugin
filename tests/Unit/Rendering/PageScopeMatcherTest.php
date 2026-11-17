<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Rendering;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Rendering\PageScopeMatcher;
use Setono\SyliusCMSPlugin\Rendering\PlacementData;
use Setono\SyliusCMSPlugin\Rendering\ReferenceLoaderInterface;
use Setono\SyliusCMSPlugin\Rendering\ReferenceMap;
use Setono\SyliusCMSPlugin\Tests\Support\Conditions;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;
use Setono\SyliusCMSPlugin\Tests\Unit\Condition\Fixture\OnProductPage;
use Setono\SyliusCMSPlugin\Tests\Unit\Condition\Fixture\ProductAvailable;
use Sylius\Component\Core\Model\Product;

/**
 * Which of a slot's placements show on the page, by their page scopes: conditions on the host-page context, combined
 * with AND, each negated or not. See "Page scopes" in docs/spec/slots.md and "Rendering a slot" in
 * docs/spec/rendering.md
 */
final class PageScopeMatcherTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_keeps_the_placements_whose_page_scope_matches_the_page_in_the_order_given(): void
    {
        $everywhere = self::placement('everywhere', []);
        $onProductPages = self::placement('on-product-pages', [self::onProductPage()]);
        $offProductPages = self::placement('off-product-pages', [self::onProductPage(negate: true)]);
        $matcher = $this->matcher();

        self::assertSame(
            [$onProductPages, $everywhere],
            $matcher->matching([$onProductPages, $offProductPages, $everywhere], Rendering::context(hostPageContext: ['product' => new Product()])),
        );
    }

    /**
     * @test
     */
    public function it_shows_a_placement_with_an_empty_scope_on_every_page_the_slot_appears_on(): void
    {
        $everywhere = self::placement('everywhere', []);

        self::assertSame([$everywhere], $this->matcher()->matching([$everywhere], Rendering::context()));
        self::assertSame([$everywhere], $this->matcher()->matching([$everywhere], Rendering::context(hostPageContext: ['product' => new Product()])));
        self::assertSame([], $this->matcher()->matching([], Rendering::context()));
    }

    /**
     * @test
     */
    public function it_combines_a_scope_s_conditions_with_and(): void
    {
        $both = self::placement('both', [self::onProductPage(), self::localeIs('da_DK')]);
        $onDanishProductPage = Rendering::context(localeCode: 'da_DK', hostPageContext: ['product' => new Product()]);
        $onEnglishProductPage = Rendering::context(hostPageContext: ['product' => new Product()]);

        self::assertSame([$both], $this->matcher()->matching([$both], $onDanishProductPage));
        self::assertSame([], $this->matcher()->matching([$both], $onEnglishProductPage));
    }

    /**
     * A page-scope condition of a kind the code no longer has is kept, and the placement doesn't show, whether it's
     * negated or not. See "Mismatches" in docs/spec/slots.md
     *
     * @test
     */
    public function it_keeps_out_a_placement_whose_scope_has_a_condition_of_a_kind_the_code_no_longer_has(): void
    {
        $removed = self::placement('removed', [['kind' => 'removed', 'negate' => false, 'params' => []]]);
        $negated = self::placement('negated', [['kind' => 'removed', 'negate' => true, 'params' => []]]);

        self::assertSame([], $this->matcher()->matching([$removed, $negated], Rendering::context(hostPageContext: ['product' => new Product()])));
    }

    /**
     * Such as a placement in a slot whose code stopped providing the product
     *
     * @test
     */
    public function it_keeps_out_a_placement_whose_scope_needs_context_the_page_doesn_t_provide_even_negated(): void
    {
        $negated = self::placement('negated', [self::onProductPage(negate: true)]);

        self::assertSame([], $this->matcher()->matching([$negated], Rendering::context()));
    }

    /**
     * @test
     */
    public function it_loads_the_references_of_every_placement_s_scope_in_one_pass_and_matches_with_them(): void
    {
        $product = new Product();
        $context = Rendering::context();
        $loader = $this->prophesize(ReferenceLoaderInterface::class);
        $loader->load(Argument::that(static function (iterable $references): bool {
            self::assertEquals([new Reference('product', 'T_SHIRT', false), new Reference('product', 'MUG', false)], [...$references]);

            return true;
        }), $context)
            ->shouldBeCalledOnce()
            ->willReturn(new ReferenceMap(['product' => ['T_SHIRT' => $product]]));

        $available = self::placement('available', [self::productAvailable('T_SHIRT')]);
        $unavailable = self::placement('unavailable', [self::productAvailable('MUG')]);

        self::assertSame([$available], $this->matcher($loader->reveal())->matching([$available, self::placement('removed', [['kind' => 'removed', 'negate' => false, 'params' => ['product' => 'CAP']]]), $unavailable], $context));
    }

    /**
     * @test
     */
    public function it_loads_nothing_when_no_placement_has_a_scope(): void
    {
        $loader = $this->prophesize(ReferenceLoaderInterface::class);
        $loader->load(Argument::cetera())->shouldNotBeCalled();
        $everywhere = self::placement('everywhere', []);

        self::assertSame([$everywhere], $this->matcher($loader->reveal())->matching([$everywhere], Rendering::context()));
    }

    private function matcher(?ReferenceLoaderInterface $loader = null): PageScopeMatcher
    {
        if (null === $loader) {
            $prophecy = $this->prophesize(ReferenceLoaderInterface::class);
            $prophecy->load(Argument::cetera())->willReturn(new ReferenceMap());
            $loader = $prophecy->reveal();
        }

        return new PageScopeMatcher(
            Conditions::matcher(['on_product_page' => new OnProductPage(), 'product_available' => new ProductAvailable()]),
            $loader,
        );
    }

    /**
     * @param list<array<string, mixed>> $pageScope
     */
    private static function placement(string $uuid, array $pageScope): PlacementData
    {
        return new PlacementData($uuid, 'snippet', 'product.after_price', $pageScope, 0);
    }

    /**
     * @return array<string, mixed>
     */
    private static function onProductPage(bool $negate = false): array
    {
        return ['kind' => 'on_product_page', 'negate' => $negate, 'params' => []];
    }

    /**
     * @return array<string, mixed>
     */
    private static function localeIs(string $locale): array
    {
        return ['kind' => 'locale_is', 'negate' => false, 'params' => ['locales' => [$locale]]];
    }

    /**
     * @return array<string, mixed>
     */
    private static function productAvailable(string $code): array
    {
        return ['kind' => 'product_available', 'negate' => false, 'params' => ['product' => $code]];
    }
}
