<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Routing;

use Setono\SyliusCMSPlugin\Routing\RouteCheckerInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;

/**
 * The route check against the test application's own routes. See "Reserved URLs" in docs/spec/urls-and-seo.md
 */
final class RouteCheckerTest extends KernelTestCase
{
    use Entries;

    /**
     * @test
     *
     * @dataProvider provideTakenUrls
     */
    public function it_names_the_sylius_route_that_takes_a_url(string $path, string $route): void
    {
        self::assertSame($route, self::checker()->takenBy(self::web(), 'en_US', $path));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideTakenUrls(): iterable
    {
        yield 'the cart' => ['cart', 'sylius_shop_cart_summary'];
        yield 'login' => ['login', 'sylius_shop_login'];
        yield 'under products' => ['products/anything', 'sylius_shop_product_show'];
    }

    /**
     * @test
     */
    public function no_route_takes_an_entrys_url_even_when_an_entry_has_it(): void
    {
        self::createPage('About us', 'about-us', 'Hello', published: true);

        self::assertNull(self::checker()->takenBy(self::web(), 'en_US', 'about-us'));
        self::assertNull(self::checker()->takenBy(null, 'en_US', 'about/team'));
    }

    /**
     * @test
     */
    public function it_leaves_the_routers_context_as_it_was(): void
    {
        /** @var RouterInterface $router */
        $router = self::getContainer()->get('router');
        $context = $router->getContext();
        $method = $context->getMethod();
        $host = $context->getHost();

        self::checker()->takenBy(self::web(), 'en_US', 'cart');

        self::assertSame($context, $router->getContext());
        self::assertSame($method, $router->getContext()->getMethod());
        self::assertSame($host, $router->getContext()->getHost());
    }

    private static function checker(): RouteCheckerInterface
    {
        $checker = self::getContainer()->get(RouteCheckerInterface::class);
        self::assertInstanceOf(RouteCheckerInterface::class, $checker);

        return $checker;
    }

    private static function web(): ChannelInterface
    {
        /** @var RepositoryInterface<ChannelInterface> $channels */
        $channels = self::getContainer()->get('sylius.repository.channel');
        $channel = $channels->findOneBy(['code' => 'WEB']);
        self::assertInstanceOf(ChannelInterface::class, $channel);

        return $channel;
    }
}
