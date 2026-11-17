<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The options of cms_image(), with their defaults. See "The image helper" in docs/spec/media.md
 */
final readonly class ImageOptions
{
    public const FORMAT_AUTO = 'auto';

    public const FORMAT_WEBP = 'webp';

    public const FORMAT_ORIGINAL = 'original';

    /**
     * @param int<1, max>|null $width the rendered width in CSS pixels, or null for the source's
     * @param float|null $aspectRatio width divided by height, or null for the source's
     * @param string|null $sizes overrides the sizes attribute the layout gives
     * @param bool $priority loads the image eagerly with a high fetch priority, for an image above the fold
     * @param string|null $alt overrides the media item's alt text, unless it's empty
     * @param string|null $class added to the <img>
     * @param self::FORMAT_* $format auto renders a <picture> with an AVIF source where the image provider supports it
     * @param list<ArtDirectedImage> $artDirection other images shown where their media queries match, in a <picture>
     * @param string|null $pictureClass renders the image in a <picture> with this class, also when it has no <source>
     */
    public function __construct(
        public ImageLayout $layout = ImageLayout::Constrained,
        public ?int $width = null,
        public ?float $aspectRatio = null,
        public ?string $sizes = null,
        public bool $priority = false,
        public ?string $alt = null,
        public ?string $class = null,
        public string $format = self::FORMAT_AUTO,
        public array $artDirection = [],
        public ?string $pictureClass = null,
    ) {
    }

    /**
     * @param array<array-key, mixed> $options as a template passes them, such as {layout: 'fixed', width: 120}
     *
     * @throws \InvalidArgumentException when an option is unknown or its value isn't one it takes
     */
    public static function fromArray(array $options): self
    {
        /** @var array{layout: string, width: int<1, max>|null, aspect_ratio: int|float|null, sizes: string|null, priority: bool, alt: string|null, class: string|null, format: self::FORMAT_*, art_direction: list<ArtDirectedImage>, picture_class: string|null} $options */
        $options = self::resolver()->resolve($options);

        $layout = ImageLayout::from($options['layout']);
        if (ImageLayout::Fixed === $layout && null === $options['width']) {
            throw new \InvalidArgumentException('cms_image() needs a width for the fixed layout');
        }

        return new self(
            $layout,
            $options['width'],
            null === $options['aspect_ratio'] ? null : (float) $options['aspect_ratio'],
            $options['sizes'],
            $options['priority'],
            $options['alt'],
            $options['class'],
            $options['format'],
            $options['art_direction'],
            $options['picture_class'],
        );
    }

    private static function resolver(): OptionsResolver
    {
        $resolver = new OptionsResolver();
        $resolver
            ->setDefaults([
                'layout' => ImageLayout::Constrained->value,
                'width' => null,
                'aspect_ratio' => null,
                'sizes' => null,
                'priority' => false,
                'alt' => null,
                'class' => null,
                'format' => self::FORMAT_AUTO,
                'art_direction' => [],
                'picture_class' => null,
            ])
            ->setAllowedValues('layout', array_map(static fn (ImageLayout $layout): string => $layout->value, ImageLayout::cases()))
            ->setAllowedTypes('width', ['null', 'int'])
            ->setAllowedValues('width', static fn (?int $width): bool => null === $width || $width > 0)
            ->setAllowedTypes('aspect_ratio', ['null', 'int', 'float'])
            ->setAllowedValues('aspect_ratio', static fn (int|float|null $ratio): bool => null === $ratio || ($ratio > 0 && is_finite((float) $ratio)))
            ->setAllowedTypes('sizes', ['null', 'string'])
            ->setAllowedTypes('priority', 'bool')
            ->setAllowedTypes('alt', ['null', 'string'])
            ->setAllowedTypes('class', ['null', 'string'])
            ->setAllowedValues('format', [self::FORMAT_AUTO, self::FORMAT_WEBP, self::FORMAT_ORIGINAL])
            ->setAllowedTypes('art_direction', 'array[]')
            ->setAllowedTypes('picture_class', ['null', 'string'])
            ->setNormalizer('art_direction', self::artDirection(...))
            ->setNormalizer('sizes', static fn (Options $options, ?string $sizes): ?string => null === $sizes || '' === trim($sizes) ? null : $sizes)
            ->setNormalizer('class', static fn (Options $options, ?string $class): ?string => null === $class || '' === trim($class) ? null : trim($class))
            ->setNormalizer('picture_class', static fn (Options $options, ?string $class): ?string => null === $class || '' === trim($class) ? null : trim($class))
        ;

        return $resolver;
    }

    /**
     * @param Options<array<string, mixed>> $options
     * @param array<array-key, array<array-key, mixed>> $images what the allowed type array[] lets through
     *
     * @return list<ArtDirectedImage>
     */
    private static function artDirection(Options $options, array $images): array
    {
        return array_values(array_map(ArtDirectedImage::fromArray(...), $images));
    }
}
