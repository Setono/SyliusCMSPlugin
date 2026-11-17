<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media\Liip;

use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Liip\ImagineBundle\Binary\BinaryInterface;
use Liip\ImagineBundle\Binary\Loader\LoaderInterface;
use Liip\ImagineBundle\Exception\Binary\Loader\NotLoadableException;
use Liip\ImagineBundle\Model\Binary;
use Symfony\Component\Mime\MimeTypesInterface;

/**
 * The LiipImagine loader named setono_sylius_cms by default (media.liip.loader): it reads the image a variant is made
 * from out of the plugin's media storage, by its storage path. The variants cached there aren't sources.
 * See "Image processing" in docs/spec/media.md
 *
 * @internal
 */
final readonly class StorageLoader implements LoaderInterface
{
    public function __construct(
        private FilesystemOperator $storage,
        private MimeTypesInterface $mimeTypes,
    ) {
    }

    /**
     * @param mixed $path the image's storage path
     *
     * @throws NotLoadableException when the storage has no such file, or it's a cached variant
     */
    public function find($path): BinaryInterface
    {
        if (!is_string($path)) {
            throw new NotLoadableException(sprintf('An image\'s path must be a string, not %s', get_debug_type($path)));
        }

        $path = ltrim($path, '/');
        if (str_starts_with($path, StorageResolver::PREFIX . '/')) {
            throw new NotLoadableException(sprintf('"%s" is an image variant, which no variant is made from', $path));
        }

        try {
            $content = $this->storage->read($path);
            $mimeType = $this->storage->mimeType($path);
        } catch (FilesystemException $exception) {
            throw new NotLoadableException(sprintf('Source image "%s" not found', $path), 0, $exception);
        }

        return new Binary($content, $mimeType, $this->mimeTypes->getExtensions($mimeType)[0] ?? null);
    }
}
