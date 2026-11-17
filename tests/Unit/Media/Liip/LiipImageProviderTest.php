<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Media\Liip;

use Imagine\Image\ImagineInterface;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Imagine\Cache\Resolver\ResolverInterface;
use Liip\ImagineBundle\Imagine\Cache\Signer;
use Liip\ImagineBundle\Imagine\Filter\FilterConfiguration;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Media\FocalPoint;
use Setono\SyliusCMSPlugin\Media\ImageFormat;
use Setono\SyliusCMSPlugin\Media\ImageTransform;
use Setono\SyliusCMSPlugin\Media\Liip\FilterSets;
use Setono\SyliusCMSPlugin\Media\Liip\LiipImageProvider;
use Setono\SyliusCMSPlugin\Media\Liip\TransformFilterLoader;
use Setono\SyliusCMSPlugin\Media\MediaKind;
use Setono\SyliusCMSPlugin\Media\MediaUrlGeneratorInterface;
use Setono\SyliusCMSPlugin\Model\MediaItem;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

final class LiipImageProviderTest extends TestCase
{
    use ProphecyTrait;

    private const PATH = '0196b2a4-7c1e-7f3a-9d2b-1a2b3c4d5e6f/9f3c2a1b4d5e6f70/summer-sale.jpg';

    private const SECRET = 'not so secret';

    /** @var ObjectProphecy<ResolverInterface> */
    private ObjectProphecy $resolver;

    /** @var ObjectProphecy<MediaUrlGeneratorInterface> */
    private ObjectProphecy $mediaUrls;

    private LiipImageProvider $provider;

    protected function setUp(): void
    {
        // The storage behind Liip's resolver is never asked whether a variant is stored, or where it is
        $this->resolver = $this->prophesize(ResolverInterface::class);
        $this->resolver->isStored(Argument::cetera())->shouldNotBeCalled();
        $this->resolver->resolve(Argument::cetera())->shouldNotBeCalled();
        $this->resolver->store(Argument::cetera())->shouldNotBeCalled();

        $cacheManager = new CacheManager(new FilterConfiguration(FilterSets::config('setono_sylius_cms', 'setono_sylius_cms')), $this->router(), new Signer(self::SECRET), new EventDispatcher());
        $cacheManager->addResolver('setono_sylius_cms', $this->resolver->reveal());

        $this->mediaUrls = $this->prophesize(MediaUrlGeneratorInterface::class);
        $this->mediaUrls->generate(Argument::type(MediaItemInterface::class))->will(static function (array $arguments): string {
            $mediaItem = $arguments[0];
            \assert($mediaItem instanceof MediaItemInterface);

            return '/media/cms/' . $mediaItem->getPath();
        });

        $this->provider = new LiipImageProvider($cacheManager, $this->mediaUrls->reveal(), $this->prophesize(ImagineInterface::class)->reveal());
    }

    /**
     * @test
     */
    public function it_builds_a_webp_variant_s_url_as_liip_s_signed_resolve_url_with_the_width_the_crop_and_the_focal_point(): void
    {
        $url = $this->provider->url(self::image(), new ImageTransform(640, 16 / 9, ImageFormat::Webp, new FocalPoint(0.25, 0.75)));

        [$filter, $hash, $path, $filters] = self::parse($url);
        self::assertSame('setono_sylius_cms_webp', $filter);
        self::assertSame(self::PATH, $path);
        self::assertSame([TransformFilterLoader::NAME => ['width' => '640', 'height' => '360', 'x' => '0.25', 'y' => '0.75']], $filters);
        self::assertTrue((new Signer(self::SECRET))->check($hash, $path, $filters), 'Liip\'s resolve step accepts the URL');
    }

    /**
     * @test
     */
    public function it_leaves_out_the_height_of_a_variant_that_keeps_the_source_s_aspect_ratio(): void
    {
        [, , , $filters] = self::parse($this->provider->url(self::image(), new ImageTransform(320, null, ImageFormat::Webp, new FocalPoint())));

        self::assertSame([TransformFilterLoader::NAME => ['width' => '320', 'x' => '0.5', 'y' => '0.5']], $filters);
    }

    /**
     * @test
     */
    public function it_builds_each_format_s_variants_with_its_own_filter_set(): void
    {
        $transform = static fn (ImageFormat $format): ImageTransform => new ImageTransform(640, null, $format, new FocalPoint());

        self::assertSame('setono_sylius_cms_avif', self::parse($this->provider->url(self::image(), $transform(ImageFormat::Avif)))[0]);
        self::assertSame('setono_sylius_cms_original', self::parse($this->provider->url(self::image(), $transform(ImageFormat::Original)))[0]);
    }

    /**
     * @test
     */
    public function it_gives_every_variant_a_new_url_when_the_file_is_replaced_or_the_focal_point_moves(): void
    {
        $image = self::image();
        $transform = new ImageTransform(640, 1.0, ImageFormat::Webp, $image->getFocalPoint());
        $before = $this->provider->url($image, $transform);

        $image->setPath('0196b2a4-7c1e-7f3a-9d2b-1a2b3c4d5e6f/0a1b2c3d4e5f6071/summer-sale.jpg');
        $replaced = $this->provider->url($image, $transform);

        $moved = $this->provider->url($image, new ImageTransform(640, 1.0, ImageFormat::Webp, new FocalPoint(0.3, 0.5)));

        self::assertCount(3, array_unique([$before, $replaced, $moved]));
        self::assertNotSame(self::parse($replaced)[1], self::parse($moved)[1], 'A moved focal point is signed into a new hash');
    }

    /**
     * @test
     */
    public function it_serves_an_svg_and_a_gif_as_uploaded_at_every_width(): void
    {
        $svg = self::image('image/svg+xml', 'logo.svg');
        $gif = self::image('image/gif', 'dancing.gif');

        self::assertSame('/media/cms/' . $svg->getPath(), $this->provider->url($svg, new ImageTransform(320, 1.0, ImageFormat::Webp, new FocalPoint())));
        self::assertSame('/media/cms/' . $gif->getPath(), $this->provider->url($gif, new ImageTransform(960, null, ImageFormat::Avif, new FocalPoint())));
    }

    /**
     * @test
     */
    public function it_offers_webp_and_the_original_format_and_avif_only_where_the_image_library_says_it_supports_it(): void
    {
        self::assertTrue($this->provider->supportsFormat(ImageFormat::Webp));
        self::assertTrue($this->provider->supportsFormat(ImageFormat::Original));
        self::assertFalse($this->provider->supportsFormat(ImageFormat::Avif), 'An image library that can\'t say what it supports isn\'t trusted with AVIF');
    }

    private static function image(string $mimeType = 'image/jpeg', string $filename = 'summer-sale.jpg'): MediaItemInterface
    {
        $image = new MediaItem();
        $image->setKind(MediaKind::Image);
        $image->setMimeType($mimeType);
        $image->setPath(str_replace('summer-sale.jpg', $filename, self::PATH));
        $image->setDimensions(2400, 1600);

        return $image;
    }

    /**
     * The filter set, the hash, the image's path and the runtime filters of a URL of Liip's resolve step
     *
     * @return array{string, string, string, array<array-key, mixed>}
     */
    private static function parse(string $url): array
    {
        if (1 !== preg_match('~^/media/cache/resolve/([^/]+)/rc/([^/]+)/([^?]+)\?(.+)$~', $url, $matches)) {
            self::fail(sprintf('%s isn\'t a URL of Liip\'s runtime resolve route', $url));
        }

        parse_str($matches[4], $query);
        self::assertIsArray($query['filters'] ?? null);

        return [$matches[1], $matches[2], rawurldecode($matches[3]), $query['filters']];
    }

    /**
     * Generates Liip's runtime resolve route, as an app that imports Liip's routing does
     */
    private function router(): RouterInterface
    {
        $routes = new RouteCollection();
        $routes->add('liip_imagine_filter_runtime', new Route('/media/cache/resolve/{filter}/rc/{hash}/{path}', requirements: ['filter' => '[A-z0-9_-]*', 'path' => '.+']));
        $generator = new UrlGenerator($routes, new RequestContext());

        $router = $this->prophesize(RouterInterface::class);
        $router->generate(Argument::type('string'), Argument::type('array'), Argument::type('int'))->will(static function (array $arguments) use ($generator): string {
            [$name, $parameters, $referenceType] = $arguments;
            \assert(is_string($name) && is_array($parameters) && is_int($referenceType));

            return $generator->generate($name, $parameters, $referenceType);
        });

        return $router->reveal();
    }
}
