<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media\Liip;

use Imagine\Driver\InfoProvider;
use Imagine\Image\ImagineInterface;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Setono\SyliusCMSPlugin\Media\ImageFormat;
use Setono\SyliusCMSPlugin\Media\ImageProviderInterface;
use Setono\SyliusCMSPlugin\Media\ImageTransform;
use Setono\SyliusCMSPlugin\Media\ImageVariants;
use Setono\SyliusCMSPlugin\Media\MediaUrlGeneratorInterface;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Builds each variant's URL as LiipImagine's signed resolve URL, with the width, the crop and the focal point as runtime
 * filters, and never asks whether the variant is stored: Liip's resolve step generates a missing variant on its first
 * request and redirects to it, so rendering a srcset costs no call to the storage, even on S3. The file's path and the
 * focal point are part of the URL, so a replaced file or a moved focal point gives every variant a new URL. SVG and GIF
 * are served as uploaded. See "Image processing" in docs/spec/media.md and ADR 0014
 */
final readonly class LiipImageProvider implements ImageProviderInterface
{
    /**
     * @param ImagineInterface $imagine the image library Liip is configured with, which says whether it writes AVIF
     */
    public function __construct(
        private CacheManager $cacheManager,
        private MediaUrlGeneratorInterface $mediaUrlGenerator,
        private ImagineInterface $imagine,
    ) {
    }

    public function url(MediaItemInterface $mediaItem, ImageTransform $transform): string
    {
        if (!ImageVariants::areMadeFor($mediaItem)) {
            return $this->mediaUrlGenerator->generate($mediaItem);
        }

        $options = ['width' => $transform->width];
        if (null !== $height = $transform->height()) {
            $options['height'] = $height;
        }

        $options['x'] = $transform->focalPoint->x;
        $options['y'] = $transform->focalPoint->y;

        return $this->cacheManager->generateUrl(
            $mediaItem->getPath(),
            FilterSets::of($transform->format),
            [TransformFilterLoader::NAME => $options],
            null,
            UrlGeneratorInterface::ABSOLUTE_PATH,
        );
    }

    public function supportsFormat(ImageFormat $format): bool
    {
        return match ($format) {
            ImageFormat::Original, ImageFormat::Webp => true,
            ImageFormat::Avif => $this->imagine instanceof InfoProvider && true === $this->imagine::getDriverInfo(false)?->isFormatSupported('avif'),
        };
    }
}
