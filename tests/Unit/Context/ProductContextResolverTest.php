<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Context;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Context\ProductContextResolver;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * The product resolver finds the product from the product page's route and slug, as Sylius's product page does.
 * See "Context resolvers" in docs/spec/slots.md
 */
final class ProductContextResolverTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ProductRepositoryInterface<ProductInterface>> */
    private ObjectProphecy $products;

    private Channel $channel;

    protected function setUp(): void
    {
        $this->channel = new Channel();
        /** @var ObjectProphecy<ProductRepositoryInterface<ProductInterface>> $products */
        $products = $this->prophesize(ProductRepositoryInterface::class);
        $this->products = $products;
    }

    /**
     * @test
     */
    public function it_finds_the_product_of_the_product_page_by_its_slug_in_the_channel_and_locale(): void
    {
        $product = new Product();
        $this->products->findOneByChannelAndSlug($this->channel, 'en_US', 't-shirt')->willReturn($product);

        self::assertSame($product, $this->resolver()->resolve(self::request('sylius_shop_product_show', 't-shirt')));
    }

    /**
     * @test
     */
    public function it_finds_nothing_for_a_slug_no_product_shown_in_the_channel_has(): void
    {
        $this->products->findOneByChannelAndSlug($this->channel, 'en_US', 'gone')->willReturn(null);

        self::assertNull($this->resolver()->resolve(self::request('sylius_shop_product_show', 'gone')));
    }

    /**
     * @test
     */
    public function it_finds_nothing_on_a_page_that_is_not_a_product_page(): void
    {
        $this->products->findOneByChannelAndSlug(Argument::cetera())->shouldNotBeCalled();

        self::assertNull($this->resolver()->resolve(self::request('sylius_shop_product_index', 't-shirts')));
        self::assertNull($this->resolver()->resolve(self::request('sylius_shop_product_show', null)));
        self::assertNull($this->resolver()->resolve(Request::create('/')));
    }

    private function resolver(): ProductContextResolver
    {
        $channelContext = $this->prophesize(ChannelContextInterface::class);
        $channelContext->getChannel()->willReturn($this->channel);
        $localeContext = $this->prophesize(LocaleContextInterface::class);
        $localeContext->getLocaleCode()->willReturn('en_US');

        return new ProductContextResolver($this->products->reveal(), $channelContext->reveal(), $localeContext->reveal());
    }

    private static function request(string $route, ?string $slug): Request
    {
        $request = Request::create('/');
        $request->attributes->set('_route', $route);
        if (null !== $slug) {
            $request->attributes->set('slug', $slug);
        }

        return $request;
    }
}
