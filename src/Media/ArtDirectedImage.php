<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Another image cms_image() shows where a media query matches, such as a Banner's mobile image on narrow screens. It's
 * rendered as a <source> of the <picture>, before the image's own. See "The image helper" in docs/spec/media.md
 */
final readonly class ArtDirectedImage
{
    /**
     * @param string $media the media query, such as (max-width: 47.99rem)
     * @param MediaItemInterface|string|null $image a media item, its UUID, or null, which shows the image's own there
     * @param float|null $aspectRatio width divided by height, or null for the art-directed image's own
     */
    public function __construct(
        public string $media,
        public MediaItemInterface|string|null $image,
        public ?float $aspectRatio = null,
    ) {
    }

    /**
     * @param array<array-key, mixed> $options as a template passes them, such as {media: '(max-width: 47.99rem)', image: block.mobileImage}
     *
     * @throws \InvalidArgumentException when an option is missing or unknown, or its value isn't one it takes
     */
    public static function fromArray(array $options): self
    {
        /** @var array{media: string, image: MediaItemInterface|string|null, aspect_ratio: int|float|null} $options */
        $options = self::resolver()->resolve($options);

        return new self(
            $options['media'],
            $options['image'],
            null === $options['aspect_ratio'] ? null : (float) $options['aspect_ratio'],
        );
    }

    private static function resolver(): OptionsResolver
    {
        $resolver = new OptionsResolver();
        $resolver
            ->setRequired(['media', 'image'])
            ->setDefault('aspect_ratio', null)
            ->setAllowedTypes('media', 'string')
            ->setAllowedValues('media', static fn (string $media): bool => '' !== trim($media))
            ->setAllowedTypes('image', ['null', 'string', MediaItemInterface::class])
            ->setAllowedTypes('aspect_ratio', ['null', 'int', 'float'])
            ->setAllowedValues('aspect_ratio', static fn (int|float|null $ratio): bool => null === $ratio || ($ratio > 0 && is_finite((float) $ratio)))
        ;

        return $resolver;
    }
}
