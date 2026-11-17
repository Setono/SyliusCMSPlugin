<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media\Liip;

/**
 * Whether Liip has made a variant, asked of the default local storage's directory with a file check: a stat, with no
 * call to the storage, as Liip's web_path resolver does for Sylius's own images. The image provider uses it only when
 * media items are stored in the default local storage and cached by the plugin's own Liip resolver.
 * See "Image processing" in docs/spec/media.md
 *
 * @internal
 */
final readonly class LocalVariants
{
    private string $directory;

    public function __construct(string $directory, private VariantPaths $variantPaths)
    {
        $this->directory = rtrim($directory, '/');
    }

    /**
     * @param string $path Liip's path of the variant, rc/{hash}/{source path} for one with runtime filters
     */
    public function isStored(string $path, string $filter): bool
    {
        return is_file($this->directory . '/' . $this->variantPaths->of($path, $filter));
    }
}
