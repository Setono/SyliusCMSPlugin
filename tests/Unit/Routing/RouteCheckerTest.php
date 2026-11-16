<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Routing;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Routing\RouteChecker;
use Sylius\Component\Core\Model\Channel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;

final class RouteCheckerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<RouterInterface> */
    private ObjectProphecy $router;

    private RequestContext $original;

    /** @var list<RequestContext> the contexts the router was given, in order */
    private array $contexts = [];

    /** @var list<array{Request, RequestContext}> the requests matched, with the router's context at the time */
    private array $matched = [];

    protected function setUp(): void
    {
        $this->original = new RequestContext('/index.php', 'POST', 'admin.example.com', 'https');
        $this->router = $this->prophesize(RouterInterface::class);
        $this->router->willImplement(RequestMatcherInterface::class);
        $this->router->getContext()->willReturn($this->original);

        $contexts = &$this->contexts;
        $this->router->setContext(Argument::type(RequestContext::class))->will(static function (array $arguments) use (&$contexts): void {
            $contexts[] = $arguments[0];
        });
        $this->router->generate('setono_sylius_cms_shop_entry', Argument::type('array'))->will(static function (array $arguments): string {
            /** @var array{_locale: string, path: string} $parameters */
            $parameters = $arguments[1];

            return sprintf('/%s/%s', $parameters['_locale'], $parameters['path']);
        });
    }

    /**
     * @test
     */
    public function it_names_the_route_that_matches_a_get_of_the_entry_url_on_the_channels_hostname(): void
    {
        $this->routeMatches(['_route' => 'sylius_shop_cart_summary', '_controller' => 'cart']);

        self::assertSame('sylius_shop_cart_summary', $this->checker()->takenBy(self::channel('shop.example.com'), 'en_US', 'cart'));

        self::assertCount(1, $this->matched);
        [$request, $context] = $this->matched[0];
        self::assertSame('https://shop.example.com/en_US/cart', $request->getUri());
        self::assertTrue($request->attributes->get(RouteChecker::REQUEST_ATTRIBUTE));
        self::assertSame('GET', $context->getMethod());
        self::assertSame('shop.example.com', $context->getHost());
        self::assertSame('', $context->getBaseUrl());
        self::assertSame('https', $context->getScheme());

        self::assertSame($this->original, $this->contexts[1] ?? null, 'The router gets its own context back');
        self::assertSame('POST', $this->original->getMethod(), 'The router\'s own context is left alone');
        self::assertSame('admin.example.com', $this->original->getHost());
        self::assertSame('/index.php', $this->original->getBaseUrl());
    }

    /**
     * @test
     */
    public function it_matches_on_the_current_host_without_a_channel_or_a_channel_without_a_hostname(): void
    {
        $this->routeMatches(['_route' => 'app_stores']);

        self::assertSame('app_stores', $this->checker()->takenBy(null, 'da_DK', 'stores/copenhagen'));
        self::assertSame('app_stores', $this->checker()->takenBy(self::channel(null), 'da_DK', 'stores/copenhagen'));

        foreach ($this->matched as [$request, $context]) {
            self::assertSame('https://admin.example.com/da_DK/stores/copenhagen', $request->getUri());
            self::assertSame('admin.example.com', $context->getHost());
        }
    }

    /**
     * @test
     */
    public function the_entry_route_takes_no_url(): void
    {
        $this->routeMatches(['_route' => 'setono_sylius_cms_shop_entry']);

        self::assertNull($this->checker()->takenBy(self::channel('shop.example.com'), 'en_US', 'about-us'));
    }

    /**
     * @test
     */
    public function a_match_without_a_route_name_takes_no_url(): void
    {
        $this->routeMatches(['_controller' => 'somewhere']);

        self::assertNull($this->checker()->takenBy(self::channel('shop.example.com'), 'en_US', 'about-us'));
    }

    /**
     * A route that only takes another method, such as a POST, doesn't take a GET of the URL
     *
     * @test
     */
    public function no_route_takes_a_url_no_route_matches_a_get_of(): void
    {
        $this->router->matchRequest(Argument::type(Request::class))->willThrow(new ResourceNotFoundException());
        self::assertNull($this->checker()->takenBy(self::channel('shop.example.com'), 'en_US', 'about-us'));

        $this->router->matchRequest(Argument::type(Request::class))->willThrow(new MethodNotAllowedException(['POST']));
        self::assertNull($this->checker()->takenBy(self::channel('shop.example.com'), 'en_US', 'cart/add'));

        self::assertSame([$this->original, $this->original], [$this->contexts[1] ?? null, $this->contexts[3] ?? null], 'The router gets its own context back');
    }

    /**
     * @test
     */
    public function a_url_the_entry_route_cannot_make_is_taken_by_no_route(): void
    {
        $this->router->generate('setono_sylius_cms_shop_entry', Argument::type('array'))->willThrow(new InvalidParameterException());
        $this->router->matchRequest(Argument::cetera())->shouldNotBeCalled();

        self::assertNull($this->checker()->takenBy(self::channel('shop.example.com'), 'not a locale', 'about-us'));
        self::assertSame($this->original, $this->contexts[1] ?? null, 'The router gets its own context back');
    }

    private function checker(): RouteChecker
    {
        /** @var RouterInterface&RequestMatcherInterface $router */
        $router = $this->router->reveal();

        return new RouteChecker($router);
    }

    /**
     * @param array<string, mixed> $match
     */
    private function routeMatches(array $match): void
    {
        $matched = &$this->matched;
        $contexts = &$this->contexts;
        /** @var ObjectProphecy<RequestMatcherInterface> $router */
        $router = $this->router;
        $router->matchRequest(Argument::type(Request::class))->will(static function (array $arguments) use ($match, &$matched, &$contexts): array {
            $context = end($contexts);
            self::assertInstanceOf(RequestContext::class, $context);
            $matched[] = [$arguments[0], $context];

            return $match;
        });
    }

    private static function channel(?string $hostname): Channel
    {
        $channel = new Channel();
        $channel->setHostname($hostname);

        return $channel;
    }
}
