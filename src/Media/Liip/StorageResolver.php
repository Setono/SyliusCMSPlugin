<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media\Liip;

use League\Flysystem\FilesystemOperator;
use Liip\ImagineBundle\Binary\BinaryInterface;
use Liip\ImagineBundle\Exception\Imagine\Cache\Resolver\NotStorableException;
use Liip\ImagineBundle\Imagine\Cache\Resolver\ResolverInterface;
use Setono\SyliusCMSPlugin\Media\MediaUrlGeneratorInterface;

/**
 * The LiipImagine resolver named setono_sylius_cms by default (media.liip.resolver): it caches image variants in the
 * plugin's media storage, where VariantPaths puts them, and resolves them to their public URLs, which
 * MediaUrlGeneratorInterface builds as it does for media items' files. See "Image processing" in docs/spec/media.md
 *
 * @internal
 */
final readonly class StorageResolver implements ResolverInterface
{
    public function __construct(
        private FilesystemOperator $storage,
        private VariantPaths $variantPaths,
        private MediaUrlGeneratorInterface $mediaUrlGenerator,
    ) {
    }

    public function isStored($path, $filter): bool
    {
        return $this->storage->fileExists($this->variantPaths->of($path, $filter));
    }

    public function resolve($path, $filter): string
    {
        return $this->mediaUrlGenerator->generate($this->variantPaths->of($path, $filter));
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

        $this->storage->write($this->variantPaths->of($path, $filter), $content, ['mimetype' => $binary->getMimeType()]);
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
                $this->storage->deleteDirectory(VariantPaths::PREFIX . '/' . $filter);

                continue;
            }

            foreach ($paths as $path) {
                [$source, $hash] = VariantPaths::split($path);
                if (null === $hash) {
                    $this->storage->deleteDirectory(VariantPaths::sourceDirectory($source, $filter));
                } else {
                    $this->storage->delete($this->variantPaths->of($path, $filter));
                }
            }
        }
    }
}
