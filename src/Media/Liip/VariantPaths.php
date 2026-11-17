<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media\Liip;

use Liip\ImagineBundle\Imagine\Filter\FilterConfiguration;

/**
 * Where an image variant is cached in the media storage: cache/{filter set}/{source path}/{hash}/{name}.{extension of the
 * format}, from the path Liip hands its resolver, which is rc/{hash}/{source path} for a variant with runtime filters.
 * All variants of a source sit under one directory, so a replaced or deleted file's variants are removed together
 *
 * @internal
 */
final readonly class VariantPaths
{
    /** The directory of the storage the variants are cached in */
    public const PREFIX = 'cache';

    /** What Liip puts before the source path of a variant with runtime filters: rc/{hash}/ */
    private const RUNTIME_PATH = '~^rc/([^/]+)/(.+)$~';

    public function __construct(private FilterConfiguration $filterConfiguration)
    {
    }

    /**
     * The storage path of the variant of Liip's path in the filter set
     */
    public function of(string $path, string $filter): string
    {
        [$source, $hash] = self::split($path);

        $format = $this->filterConfiguration->get($filter)['format'] ?? null;
        $extension = is_string($format) && '' !== $format ? $format : pathinfo($source, \PATHINFO_EXTENSION);
        $name = pathinfo($source, \PATHINFO_FILENAME) . ('' === $extension ? '' : '.' . $extension);

        return self::sourceDirectory($source, $filter) . (null === $hash ? '' : '/' . $hash) . '/' . $name;
    }

    /**
     * The directory every variant of the source in the filter set is cached under
     */
    public static function sourceDirectory(string $source, string $filter): string
    {
        return self::PREFIX . '/' . $filter . '/' . $source;
    }

    /**
     * The source path, and the hash of the runtime filters when the path has them
     *
     * @return array{string, string|null}
     */
    public static function split(string $path): array
    {
        $path = ltrim($path, '/');

        return 1 === preg_match(self::RUNTIME_PATH, $path, $matches) ? [$matches[2], $matches[1]] : [$path, null];
    }
}
