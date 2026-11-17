<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Twig;

use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Media\FocalPoint;
use Setono\SyliusCMSPlugin\Media\MediaKind;
use Setono\SyliusCMSPlugin\Media\MediaUrlGeneratorInterface;
use Setono\SyliusCMSPlugin\Media\PermanentUrlGeneratorInterface;
use Setono\SyliusCMSPlugin\Model\MediaItem;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Rendering\RenderContextFactoryInterface;
use Setono\SyliusCMSPlugin\Repository\MediaItemRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Unit\Twig\ImageFixture\FakeImageProvider;
use Setono\SyliusCMSPlugin\Twig\CMSExtension;
use Setono\SyliusCMSPlugin\Twig\CMSRuntime;
use Setono\SyliusCMSPlugin\Twig\MediaRuntime;
use Setono\SyliusCMSPlugin\Twig\VideoRuntime;
use Sylius\Component\Core\Model\Channel;
use Twig\RuntimeLoader\FactoryRuntimeLoader;
use Twig\Test\IntegrationTestCase;

/**
 * Runs the .test files in VideoFixture: cms_video() and cms_media_url(). Their --DATA-- sections build the media items
 * with the helpers below. A file's URL is /media/cms/{path}, a poster's comes from FakeImageProvider, as
 * /{format}/{width}x{height}/{path}, and a permanent URL is https://localhost/media/permanent/{original filename}
 */
final class VideoExtensionTest extends IntegrationTestCase
{
    use ProphecyTrait;

    /** @var array<string, MediaItemInterface> the media items the library has, by UUID */
    private array $library = [];

    protected function setUp(): void
    {
        $this->library = [];
    }

    protected static function getFixturesDirectory(): string
    {
        return __DIR__ . '/VideoFixture';
    }

    /**
     * Twig before 3.13, such as the lowest 3.7 the plugin allows, reads the fixtures from here
     */
    protected function getFixturesDir(): string
    {
        return self::getFixturesDirectory();
    }

    /**
     * @return list<CMSExtension>
     */
    protected function getExtensions(): array
    {
        return [new CMSExtension()];
    }

    /**
     * @return list<FactoryRuntimeLoader>
     */
    protected function getRuntimeLoaders(): array
    {
        return [new FactoryRuntimeLoader([
            VideoRuntime::class => fn (): VideoRuntime => new VideoRuntime(
                $this->mediaUrls(),
                new FakeImageProvider(false),
                new FakeImageProvider(false),
                $this->mediaItems(),
                $this->shopRenderContext(),
            ),
            MediaRuntime::class => fn (): MediaRuntime => new MediaRuntime($this->permanentUrls(), $this->mediaItems()),
        ])];
    }

    /**
     * The variables of a block template: the given ones and the render context
     *
     * @param array<string, mixed> $variables
     * @param RenderContext::PREVIEW_MODE_*|null $previewMode
     *
     * @return array<string, mixed>
     */
    public function rendering(array $variables, ?string $previewMode = null): array
    {
        return $variables + [
            CMSRuntime::RENDER_CONTEXT => new RenderContext(new Channel(), 'en_US', 'USD', null, '@guest', new \DateTimeImmutable(), [], $previewMode),
        ];
    }

    /**
     * A 1920 by 1080 MP4, with this poster
     */
    public function video(?MediaItemInterface $poster = null): MediaItemInterface
    {
        $video = self::mediaItem(MediaKind::Video, 'video/mp4', 'shop-tour.mp4', 1920, 1080);
        $video->setPoster($poster);

        return $video;
    }

    /**
     * A JPEG of this size, with this focal point
     */
    public function image(string $filename = 'tour-poster.jpg', int $width = 2400, int $height = 1600, ?FocalPoint $focalPoint = null): MediaItemInterface
    {
        $image = self::mediaItem(MediaKind::Image, 'image/jpeg', $filename, $width, $height);
        if (null !== $focalPoint) {
            $image->setFocalPoint($focalPoint);
        }

        return $image;
    }

    public function svg(): MediaItemInterface
    {
        return self::mediaItem(MediaKind::Image, 'image/svg+xml', 'logo.svg', 120, 40);
    }

    public function document(): MediaItemInterface
    {
        return self::mediaItem(MediaKind::Document, 'application/pdf', 'size-guide.pdf', null, null);
    }

    /**
     * Puts the media item in the library and gives its UUID
     */
    public function inTheLibrary(MediaItemInterface $mediaItem): string
    {
        $this->library[$mediaItem->getUuid()] = $mediaItem;

        return $mediaItem->getUuid();
    }

    private static function mediaItem(MediaKind $kind, string $mimeType, string $path, ?int $width, ?int $height): MediaItemInterface
    {
        $mediaItem = new MediaItem();
        $mediaItem->setKind($kind);
        $mediaItem->setMimeType($mimeType);
        $mediaItem->setPath($path);
        $mediaItem->setOriginalFilename($path);
        $mediaItem->setDimensions($width, $height);

        return $mediaItem;
    }

    private function mediaItems(): MediaItemRepositoryInterface
    {
        // Prophecy binds the promise to the double, so the library is reached through a reference
        $library = &$this->library;
        $mediaItems = $this->prophesize(MediaItemRepositoryInterface::class);
        $mediaItems->findOneByUuid(Argument::type('string'))->will(static function (array $arguments) use (&$library): ?MediaItemInterface {
            $uuid = $arguments[0];
            \assert(is_string($uuid));

            return $library[$uuid] ?? null;
        });

        return $mediaItems->reveal();
    }

    private function mediaUrls(): MediaUrlGeneratorInterface
    {
        $mediaUrls = $this->prophesize(MediaUrlGeneratorInterface::class);
        $mediaUrls->generate(Argument::type(MediaItemInterface::class))->will(static function (array $arguments): string {
            $mediaItem = $arguments[0];
            \assert($mediaItem instanceof MediaItemInterface);

            return '/media/cms/' . $mediaItem->getPath();
        });

        return $mediaUrls->reveal();
    }

    private function permanentUrls(): PermanentUrlGeneratorInterface
    {
        $permanentUrls = $this->prophesize(PermanentUrlGeneratorInterface::class);
        $permanentUrls->generate(Argument::type(MediaItemInterface::class))->will(static function (array $arguments): ?string {
            $mediaItem = $arguments[0];
            \assert($mediaItem instanceof MediaItemInterface);

            return MediaKind::Image === $mediaItem->getKind() ? null : 'https://localhost/media/permanent/' . $mediaItem->getOriginalFilename();
        });

        return $permanentUrls->reveal();
    }

    /**
     * The render context of a page in the shop outside the CMS's templates
     */
    private function shopRenderContext(): RenderContextFactoryInterface
    {
        $factory = $this->prophesize(RenderContextFactoryInterface::class);
        $factory->create()->willReturn(new RenderContext(new Channel(), 'en_US', 'USD', null, '@guest', new \DateTimeImmutable()));

        return $factory->reveal();
    }
}
