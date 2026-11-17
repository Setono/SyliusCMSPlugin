<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media\Liip;

use Setono\SyliusCMSPlugin\Media\ImageFormat;

/**
 * The LiipImagine filter sets image variants are made with, one per format, since Liip takes the format from the filter
 * set and only the filters from a URL. Each reads with the configured loader and caches with the configured resolver.
 * SetonoSyliusCMSExtension prepends them to liip_imagine.filter_sets, so a project changes their quality there
 *
 * @internal
 */
final class FilterSets
{
    private function __construct()
    {
    }

    public static function of(ImageFormat $format): string
    {
        return 'setono_sylius_cms_' . $format->value;
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_map(self::of(...), ImageFormat::cases());
    }

    /**
     * @param string $loader the name of the Liip loader they read the source images with
     * @param string $resolver the name of the Liip resolver they cache the variants with
     *
     * @return array<string, array{data_loader: string, cache: string, quality: int, format: string|null, filters: array<string, array<string, mixed>>, post_processors: array<string, array<string, mixed>>}>
     */
    public static function config(string $loader, string $resolver): array
    {
        $set = static fn (?string $format, int $quality): array => [
            'data_loader' => $loader,
            'cache' => $resolver,
            'quality' => $quality,
            'format' => $format,
            'filters' => [],
            'post_processors' => [],
        ];

        return [
            self::of(ImageFormat::Webp) => $set('webp', 80),
            self::of(ImageFormat::Avif) => $set('avif', 60),
            self::of(ImageFormat::Original) => $set(null, 85),
        ];
    }
}
