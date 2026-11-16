<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

use Symfony\Component\Mime\MimeTypesInterface;

/**
 * Reads a file's MIME type from its content, never from its name. libmagic names an SVG in several ways, depending on
 * its version and on how the SVG starts, so an XML or text file whose root element is <svg> is an SVG.
 * See "Uploading" in docs/spec/media.md
 */
final readonly class MimeTypeDetector
{
    public const SVG = 'image/svg+xml';

    /** What libmagic may call an SVG */
    private const SVG_CANDIDATES = ['image/svg+xml', 'image/svg', 'text/xml', 'application/xml', 'text/plain', 'text/html'];

    /** The comments, processing instructions and doctype that may come before the root element */
    private const PROLOG = '/\A(?:\s+|<\?.*?\?>|<!--.*?-->|<!DOCTYPE[^>\[]*(?:\[.*?\])?\s*>)*/si';

    public function __construct(private MimeTypesInterface $mimeTypes)
    {
    }

    public function detect(string $path): string
    {
        $mimeType = $this->mimeTypes->guessMimeType($path) ?? 'application/octet-stream';

        if (in_array($mimeType, self::SVG_CANDIDATES, true) && self::hasSvgRoot($path)) {
            return self::SVG;
        }

        return $mimeType;
    }

    /**
     * The extension a stored file of this MIME type gets, such as jpg for image/jpeg, or bin for one with none known
     */
    public function extension(string $mimeType): string
    {
        return $this->mimeTypes->getExtensions($mimeType)[0] ?? 'bin';
    }

    private static function hasSvgRoot(string $path): bool
    {
        // The root element comes early, unless a file is mostly comments, which no SVG worth uploading is
        $start = (string) file_get_contents($path, false, null, 0, 64 * 1024);
        // A UTF-8 byte order mark
        if (str_starts_with($start, "\xEF\xBB\xBF")) {
            $start = substr($start, 3);
        }

        $start = (string) preg_replace(self::PROLOG, '', $start, 1);

        return 1 === preg_match('/\A<(?:[A-Za-z_][\w.-]*:)?svg[\s>\/]/', $start);
    }
}
