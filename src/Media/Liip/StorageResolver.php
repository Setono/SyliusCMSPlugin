<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media\Liip;

use League\Flysystem\FilesystemOperator;
use Liip\ImagineBundle\Binary\BinaryInterface;
use Liip\ImagineBundle\Exception\Imagine\Cache\Resolver\NotResolvableException;
use Liip\ImagineBundle\Exception\Imagine\Cache\Resolver\NotStorableException;
use Liip\ImagineBundle\Imagine\Cache\Resolver\ResolverInterface;
use Liip\ImagineBundle\Imagine\Filter\FilterConfiguration;

/**
 * The LiipImagine resolver named setono_sylius_cms by default (media.liip.resolver): it caches image variants in the
 * plugin's media storage, under cache/{filter set}/{source path}/{hash}/{name}.{extension of the format}, and resolves
 * them to the storage's public URLs. All variants of a source sit under one directory, so a replaced or deleted file's
 * variants are removed together. See "Image processing" in docs/spec/media.md
 *
 * @internal
 */
final readonly class StorageResolver implements ResolverInterface
{
    /** The directory of the storage the variants are cached in */
    public const PREFIX = 'cache';

    /** What Liip puts before the source path of a variant with runtime filters: rc/{hash}/ */
    private const RUNTIME_PATH = '~^rc/([^/]+)/(.+)$~';

    public function __construct(
        private FilesystemOperator $storage,
        private FilterConfiguration $filterConfiguration,
    ) {
    }

    public function isStored($path, $filter): bool
    {
        return $this->storage->fileExists($this->variantPath($path, $filter));
    }

    public function resolve($path, $filter): string
    {
        // FilesystemOperator only declares publicUrl() in a @method tag, so a storage of a project's own may not have it
        // @phpstan-ignore function.alreadyNarrowedType
        if (!method_exists($this->storage, 'publicUrl')) {
            throw new NotResolvableException(sprintf('The media storage, a %s, has no public URLs. Give it a public_url option, or configure another resolver in setono_sylius_cms.media.liip.resolver', get_debug_type($this->storage)));
        }

        $url = $this->storage->publicUrl($this->variantPath($path, $filter));
        \assert(is_string($url));

        return $url;
    }

    /**
     * @throws NotStorableException when the variant has no content
     */
    public function store(BinaryInterface $binary, $path, $filter): void
    {
        $content = $binary->getContent();
        if (!is_string($content)) {
            throw new NotStorableException(sprintf('The variant of "%s" in the filter set "%s" has no content to store', $path, $filter));
        }

        $this->storage->write($this->variantPath($path, $filter), $content, ['mimetype' => $binary->getMimeType()]);
    }

    /**
     * Removes the variants of the paths in the filter sets: of a source path, every variant of it, and of a path with
     * runtime filters, that one variant. Without paths, every variant of the filter sets
     *
     * @param array<string> $paths
     * @param array<string> $filters
     */
    public function remove(array $paths, array $filters): void
    {
        foreach ($filters as $filter) {
            if ([] === $paths) {
                $this->storage->deleteDirectory(self::PREFIX . '/' . $filter);

                continue;
            }

            foreach ($paths as $path) {
                [$source, $hash] = self::split($path);
                if (null === $hash) {
                    $this->storage->deleteDirectory(self::sourceDirectory($source, $filter));
                } else {
                    $this->storage->delete($this->variantPath($path, $filter));
                }
            }
        }
    }

    private function variantPath(string $path, string $filter): string
    {
        [$source, $hash] = self::split($path);

        $format = $this->filterConfiguration->get($filter)['format'] ?? null;
        $extension = is_string($format) && '' !== $format ? $format : pathinfo($source, \PATHINFO_EXTENSION);
        $name = pathinfo($source, \PATHINFO_FILENAME) . ('' === $extension ? '' : '.' . $extension);

        return self::sourceDirectory($source, $filter) . (null === $hash ? '' : '/' . $hash) . '/' . $name;
    }

    private static function sourceDirectory(string $source, string $filter): string
    {
        return self::PREFIX . '/' . $filter . '/' . $source;
    }

    /**
     * The source path, and the hash of the runtime filters when the path has them
     *
     * @return array{string, string|null}
     */
    private static function split(string $path): array
    {
        $path = ltrim($path, '/');

        return 1 === preg_match(self::RUNTIME_PATH, $path, $matches) ? [$matches[2], $matches[1]] : [$path, null];
    }
}
