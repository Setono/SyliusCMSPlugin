<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Media\Liip;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Media\Liip\VariantRedirectCacheListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * A variant's runtime URL names its file's path and its focal point, so it leads to the same variant for as long as it
 * exists: browsers and CDNs may keep its redirect for a year. Liip's other redirects, such as for Sylius's product
 * images, which are replaced at the same path, keep what Liip sends. See "Image processing" in docs/spec/media.md
 */
final class VariantRedirectCacheListenerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_lets_cdns_keep_the_redirect_of_a_variant_s_runtime_url_for_a_year_and_browsers_ask_again(): void
    {
        $response = $this->respond('liip_imagine_filter_runtime', 'setono_sylius_cms_webp', new RedirectResponse('/media/cms/cache/setono_sylius_cms_webp/a/b/c.jpg/Ab3dE6gH/c.webp'));

        self::assertSame(302, $response->getStatusCode(), 'Liip\'s redirect status is kept');
        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertSame('0', $response->headers->getCacheControlDirective('max-age'));
        self::assertSame('31536000', $response->headers->getCacheControlDirective('s-maxage'));
        self::assertFalse($response->headers->hasCacheControlDirective('immutable'), 'A browser can\'t be purged after liip:imagine:cache:remove');
    }

    /**
     * @test
     *
     * @dataProvider othersResponses
     */
    public function it_leaves_every_other_response_as_it_is(string $route, string $filter, Response $response): void
    {
        $cacheControl = $response->headers->get('Cache-Control');

        self::assertSame($cacheControl, $this->respond($route, $filter, $response)->headers->get('Cache-Control'));
    }

    /**
     * @return iterable<string, array{string, string, Response}>
     */
    public static function othersResponses(): iterable
    {
        yield 'a Sylius product image\'s variant' => ['liip_imagine_filter_runtime', 'sylius_shop_product_thumbnail', new RedirectResponse('/media/cache/sylius_shop_product_thumbnail/ab/cd/shoe.jpg')];
        yield 'Liip\'s URL without runtime filters' => ['liip_imagine_filter', 'setono_sylius_cms_webp', new RedirectResponse('/media/cms/cache/setono_sylius_cms_webp/a/b/c.jpg/c.webp')];
        yield 'a runtime URL whose signature is wrong' => ['liip_imagine_filter_runtime', 'setono_sylius_cms_webp', new Response('Signed url does not pass the sign check', 400)];
    }

    private function respond(string $route, string $filter, Response $response): Response
    {
        $request = new Request(attributes: ['_route' => $route, 'filter' => $filter]);
        $event = new ResponseEvent($this->prophesize(HttpKernelInterface::class)->reveal(), $request, HttpKernelInterface::MAIN_REQUEST, $response);

        (new VariantRedirectCacheListener())($event);

        return $event->getResponse();
    }
}
