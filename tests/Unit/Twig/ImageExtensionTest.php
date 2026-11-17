<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Twig;

use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Localization\FallbackChain;
use Setono\SyliusCMSPlugin\Media\AltTextResolver;
use Setono\SyliusCMSPlugin\Media\FocalPoint;
use Setono\SyliusCMSPlugin\Media\MediaKind;
use Setono\SyliusCMSPlugin\Model\MediaItem;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Rendering\RenderContextFactoryInterface;
use Setono\SyliusCMSPlugin\Repository\MediaItemRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;
use Setono\SyliusCMSPlugin\Tests\Unit\Twig\ImageFixture\FakeImageProvider;
use Setono\SyliusCMSPlugin\Twig\CMSExtension;
use Setono\SyliusCMSPlugin\Twig\CMSRuntime;
use Setono\SyliusCMSPlugin\Twig\ImageRuntime;
use Setono\SyliusCMSPlugin\Twig\MediaItemArguments;
use Setono\SyliusCMSPlugin\Twig\RenderContexts;
use Sylius\Resource\Translation\Provider\ImmutableTranslationLocaleProvider;
use Twig\RuntimeLoader\FactoryRuntimeLoader;
use Twig\Test\IntegrationTestCase;

/**
 * Runs the .test files in ImageFixture: cms_image(). Their --DATA-- sections build the media items with the helpers
 * below. Variant URLs come from FakeImageProvider, as /{format}/{width}[x{height}]/{path}, and the widths are the
 * default media.widths: 320, 640, 960, 1280, 1600, 1920 and 2560
 */
final class ImageExtensionTest extends IntegrationTestCase
{
    use ProphecyTrait;

    private bool $avif = false;

    /** @var array<string, MediaItemInterface> the media items the library has, by UUID */
    private array $library = [];

    protected function setUp(): void
    {
        $this->avif = false;
        $this->library = [];
    }

    protected static function getFixturesDirectory(): string
    {
        return __DIR__ . '/ImageFixture';
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
     * The runtime is built when a template first calls cms_image(), after --DATA-- ran
     *
     * @return list<FactoryRuntimeLoader>
     */
    protected function getRuntimeLoaders(): array
    {
        return [new FactoryRuntimeLoader([
            ImageRuntime::class => fn (): ImageRuntime => new ImageRuntime(
                new FakeImageProvider($this->avif),
                new FakeImageProvider($this->avif),
                // de_CH falls back to de_DE, and then to en_US
                new AltTextResolver(new FallbackChain(new ImmutableTranslationLocaleProvider(['en_US'], 'en_US'), ['de_CH' => ['de_DE']])),
                new MediaItemArguments($this->mediaItems()),
                new RenderContexts($this->shopRenderContext()),
                [320, 640, 960, 1280, 1600, 1920, 2560],
            ),
        ])];
    }

    /**
     * The variables of a layout or block template: the given ones and the render context
     *
     * @param array<string, mixed> $variables
     * @param RenderContext::PREVIEW_MODE_*|null $previewMode
     *
     * @return array<string, mixed>
     */
    public function rendering(array $variables, ?string $previewMode = null, string $locale = 'en_US'): array
    {
        return $variables + [
            CMSRuntime::RENDER_CONTEXT => Rendering::context(localeCode: $locale, previewMode: $previewMode),
        ];
    }

    /**
     * Makes the image provider give AVIF variants, as where the server's image library writes AVIF, and gives no
     * variables
     *
     * @return array{}
     */
    public function withAvif(): array
    {
        $this->avif = true;

        return [];
    }

    /**
     * A JPEG of this size, with this alt text by locale and this focal point
     *
     * @param array<string, string> $altTexts
     */
    public function image(int $width = 2400, int $height = 1600, array $altTexts = ['en_US' => 'A beach at dawn'], ?FocalPoint $focalPoint = null): MediaItemInterface
    {
        return self::mediaItem(MediaKind::Image, 'image/jpeg', 'summer-sale.jpg', $width, $height, $altTexts, $focalPoint);
    }

    /**
     * A JPEG for narrow screens, such as a Banner's mobile image, with no alt text of its own
     */
    public function mobileImage(int $width = 800, int $height = 1000): MediaItemInterface
    {
        return self::mediaItem(MediaKind::Image, 'image/jpeg', 'summer-sale-mobile.jpg', $width, $height);
    }

    public function svg(?int $width = 120, ?int $height = 40): MediaItemInterface
    {
        return self::mediaItem(MediaKind::Image, 'image/svg+xml', 'logo.svg', $width, $height, ['en_US' => 'Our logo']);
    }

    public function gif(): MediaItemInterface
    {
        return self::mediaItem(MediaKind::Image, 'image/gif', 'dancing.gif', 480, 270, ['en_US' => 'A dancing cat']);
    }

    public function video(): MediaItemInterface
    {
        return self::mediaItem(MediaKind::Video, 'video/mp4', 'tour.mp4', 1920, 1080);
    }

    /**
     * Puts the media item in the library and gives its UUID
     */
    public function inTheLibrary(MediaItemInterface $mediaItem): string
    {
        $this->library[$mediaItem->getUuid()] = $mediaItem;

        return $mediaItem->getUuid();
    }

    /**
     * @param array<string, string> $altTexts
     */
    private static function mediaItem(MediaKind $kind, string $mimeType, string $path, ?int $width, ?int $height, array $altTexts = [], ?FocalPoint $focalPoint = null): MediaItemInterface
    {
        $mediaItem = new MediaItem();
        $mediaItem->setKind($kind);
        $mediaItem->setMimeType($mimeType);
        $mediaItem->setPath($path);
        $mediaItem->setDimensions($width, $height);
        foreach ($altTexts as $locale => $altText) {
            $mediaItem->setAltText($locale, $altText);
        }

        if (null !== $focalPoint) {
            $mediaItem->setFocalPoint($focalPoint);
        }

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

    /**
     * The render context of a page in the shop outside the CMS's templates: in de_DE
     */
    private function shopRenderContext(): RenderContextFactoryInterface
    {
        $factory = $this->prophesize(RenderContextFactoryInterface::class);
        $factory->create()->willReturn(Rendering::context(localeCode: 'de_DE', currencyCode: 'EUR'));

        return $factory->reveal();
    }
}
