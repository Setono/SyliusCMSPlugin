<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig;

use Setono\SyliusCMSPlugin\Media\ImageFormat;
use Setono\SyliusCMSPlugin\Media\ImageFormatsInterface;
use Setono\SyliusCMSPlugin\Media\ImageProviderInterface;
use Setono\SyliusCMSPlugin\Media\ImageTransform;
use Setono\SyliusCMSPlugin\Media\ImageVariants;
use Setono\SyliusCMSPlugin\Media\MediaKind;
use Setono\SyliusCMSPlugin\Media\MediaUrlGeneratorInterface;
use Setono\SyliusCMSPlugin\Media\Video\VideoEmbed;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Uid\Uuid;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * cms_video(): renders a video the way the Video block shows it, a video media item as a <video> of its current file
 * with a poster, or a YouTube or Vimeo URL as the provider's privacy-enhanced embed. See "Video" in docs/spec/blocks.md
 */
final readonly class VideoRuntime implements RuntimeExtensionInterface
{
    /** The aspect ratio the Video block's frame has, which a poster is cropped to */
    private const ASPECT_RATIO = 16 / 9;

    /** The widest a poster is, since it's one image for every screen */
    private const POSTER_WIDTH = 1280;

    /** What an embed may use: what YouTube's and Vimeo's own embed codes allow */
    private const EMBED_ALLOW = 'accelerometer; autoplay; clipboard-write; encrypted-media; fullscreen; gyroscope; picture-in-picture; web-share';

    public function __construct(
        private MediaUrlGeneratorInterface $mediaUrlGenerator,
        private ImageProviderInterface $imageProvider,
        private ImageFormatsInterface $imageFormats,
        private MediaItemArguments $mediaItems,
        private RenderContexts $renderContexts,
    ) {
    }

    /**
     * Prints nothing for null, a broken reference, a media item that isn't a video, or a URL that isn't a YouTube or
     * Vimeo video. In the builder's preview, it prints a grey 16:9 placeholder instead
     *
     * Options: poster (an image media item or its UUID, over the video media item's own poster), title (an embed's
     * iframe title, which names it to screen readers) and class (added to the element)
     *
     * @param array<string, mixed> $context
     * @param MediaItemInterface|string|null $video a video media item, its UUID, or a YouTube or Vimeo URL
     * @param array<array-key, mixed> $options
     */
    public function video(array $context, MediaItemInterface|string|null $video, array $options = []): string
    {
        /** @var array{poster: MediaItemInterface|string|null, title: string|null, class: string|null} $options */
        $options = self::resolver()->resolve($options);

        if (is_string($video) && !Uuid::isValid($video)) {
            $embed = VideoEmbed::fromUrl($video);
            if (null !== $embed) {
                return HtmlTag::open('iframe', [
                    'src' => $embed->src(),
                    'title' => $options['title'],
                    'class' => $options['class'],
                    'allow' => self::EMBED_ALLOW,
                    'referrerpolicy' => 'strict-origin-when-cross-origin',
                    'loading' => 'lazy',
                    'allowfullscreen' => true,
                ]) . '</iframe>';
            }
        } else {
            $mediaItem = $this->mediaItems->mediaItem($video, MediaKind::Video);
            if (null !== $mediaItem) {
                $poster = $this->mediaItems->mediaItem($options['poster'], MediaKind::Image) ?? $this->mediaItems->mediaItem($mediaItem->getPoster(), MediaKind::Image);

                return HtmlTag::open('video', [
                    'src' => $this->mediaUrlGenerator->generate($mediaItem),
                    'poster' => null === $poster ? null : $this->posterUrl($poster),
                    'width' => $mediaItem->getWidth(),
                    'height' => $mediaItem->getHeight(),
                    'class' => $options['class'],
                    'preload' => 'metadata',
                    'controls' => true,
                    'playsinline' => true,
                ]) . '</video>';
            }
        }

        return RenderContext::PREVIEW_MODE_BUILDER === $this->renderContexts->of($context)->previewMode ? ImagePlaceholder::html(self::ASPECT_RATIO, [$options['class']]) : '';
    }

    /**
     * One poster for every screen: a WebP variant cropped to 16:9 around the focal point, at most POSTER_WIDTH wide and
     * never wider than the crop, or the image as uploaded when it has no variants, as an SVG hasn't
     */
    private function posterUrl(MediaItemInterface $poster): string
    {
        $width = $poster->getWidth();
        $height = $poster->getHeight();
        if (!ImageVariants::areMadeFor($poster) || null === $width || null === $height) {
            return $this->imageProvider->url($poster, new ImageTransform(max(1, $width ?? 1), null, ImageFormat::Original, $poster->getFocalPoint()));
        }

        $format = $this->imageFormats->supports(ImageFormat::Webp) ? ImageFormat::Webp : ImageFormat::Original;
        $posterWidth = max(1, min(self::POSTER_WIDTH, $width, (int) floor($height * self::ASPECT_RATIO)));

        return $this->imageProvider->url($poster, new ImageTransform($posterWidth, self::ASPECT_RATIO, $format, $poster->getFocalPoint()));
    }

    private static function resolver(): OptionsResolver
    {
        $resolver = new OptionsResolver();
        $resolver
            ->setDefaults(['poster' => null, 'title' => null, 'class' => null])
            ->setAllowedTypes('poster', ['null', 'string', MediaItemInterface::class])
            ->setAllowedTypes('title', ['null', 'string'])
            ->setAllowedTypes('class', ['null', 'string'])
        ;

        return $resolver;
    }
}
