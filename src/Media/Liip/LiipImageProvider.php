<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media\Liip;

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Setono\SyliusCMSPlugin\Media\ImageProviderInterface;
use Setono\SyliusCMSPlugin\Media\ImageTransform;
use Setono\SyliusCMSPlugin\Media\ImageVariants;
use Setono\SyliusCMSPlugin\Media\MediaUrlGeneratorInterface;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Builds each variant's URL with LiipImagine, with the width, the crop and the focal point as runtime filters, and never
 * calls the storage. On the default local storage, a variant Liip has made gets its own URL, which the web server serves
 * without PHP, once a file check finds it, as Sylius's own Liip setup does. Otherwise, and always on another storage
 * such as S3, it gets Liip's signed runtime URL, whose resolve step makes a missing variant on its first request and
 * redirects to it. The file's path and the focal point are part of both URLs, so a replaced file or a moved focal point
 * gives every variant a new URL. SVG and GIF are served as uploaded. See "Image processing" in docs/spec/media.md and
 * ADR 0014
 */
final readonly class LiipImageProvider implements ImageProviderInterface
{
    public function __construct(
        private CacheManager $cacheManager,
        private MediaUrlGeneratorInterface $mediaUrlGenerator,
        private ?LocalVariants $localVariants = null,
    ) {
    }

    public function url(MediaItemInterface $mediaItem, ImageTransform $transform): string
    {
        if (!ImageVariants::areMadeFor($mediaItem)) {
            return $this->mediaUrlGenerator->generate($mediaItem->getPath());
        }

        $options = ['width' => $transform->width];
        if (null !== $height = $transform->height()) {
            $options['height'] = $height;
        }

        $options['x'] = $transform->focalPoint->x;
        $options['y'] = $transform->focalPoint->y;

        $filter = FilterSets::of($transform->format);
        $runtimeConfig = [TransformFilterLoader::NAME => $options];

        if (null !== $this->localVariants) {
            $runtimePath = $this->cacheManager->getRuntimePath($mediaItem->getPath(), $runtimeConfig);
            if ($this->localVariants->isStored($runtimePath, $filter)) {
                return $this->cacheManager->resolve($runtimePath, $filter);
            }
        }

        return $this->cacheManager->generateUrl($mediaItem->getPath(), $filter, $runtimeConfig, null, UrlGeneratorInterface::ABSOLUTE_PATH);
    }
}
