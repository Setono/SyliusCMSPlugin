<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

/**
 * The public URL of a file in the plugin's media storage, by its storage path: a media item's current file, or an image
 * variant cached beside it. The permanent URL of documents and videos redirects to a media item's, and the library
 * shows it. The image provider asks it for the URLs of SVGs, GIFs and stored variants. The default builds URLs under
 * /media/cms, where the default storage is served, and a project whose storage is elsewhere, such as on S3, aliases this
 * interface to its own service. Building a URL never calls the storage
 */
interface MediaUrlGeneratorInterface
{
    public function generate(string $path): string;
}
