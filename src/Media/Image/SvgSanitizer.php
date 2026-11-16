<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media\Image;

use enshrined\svgSanitize\Sanitizer;
use Setono\SyliusCMSPlugin\Media\UnreadableFileException;

/**
 * Sanitises an SVG on upload with enshrined/svg-sanitize, the sanitiser most PHP CMSs use: only the elements and
 * attributes that draw are kept, so no scripts, event handlers, javascript: links or embedded HTML, and references to
 * files elsewhere go too. Media items are public, so an SVG could otherwise run scripts on the shop's own origin.
 * See "What the library holds" in docs/spec/media.md
 */
final class SvgSanitizer
{
    private const XLINK = 'http://www.w3.org/1999/xlink';

    /**
     * @throws UnreadableFileException when the SVG isn't well-formed XML
     */
    public function sanitize(string $svg): string
    {
        $sanitizer = new Sanitizer();
        $sanitizer->removeRemoteReferences(true);

        $sanitized = $sanitizer->sanitize($svg);
        if (!is_string($sanitized) || '' === trim($sanitized)) {
            throw new UnreadableFileException('The SVG isn\'t well-formed XML');
        }

        return self::removeRemoteFiles($sanitized);
    }

    /**
     * The sanitiser only removes references to files elsewhere in url(…) values, so this removes those in an href or
     * xlink:href, such as an <image> or a <use> that loads another site's file. Links (<a>) keep theirs, and a fragment
     * (#…) or an embedded data: URI stays
     */
    private static function removeRemoteFiles(string $svg): string
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $document = new \DOMDocument();
            $loaded = $document->loadXML($svg, \LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (!$loaded) {
            throw new UnreadableFileException('The SVG isn\'t well-formed XML');
        }

        $removed = false;
        foreach ($document->getElementsByTagName('*') as $element) {
            if ('a' === strtolower($element->localName ?? '')) {
                continue;
            }

            foreach ([[null, 'href'], [self::XLINK, 'href']] as [$namespace, $name]) {
                $value = null === $namespace ? $element->getAttribute($name) : $element->getAttributeNS($namespace, $name);
                if (1 === preg_match('#^\s*(?:(?!data:)[a-z][a-z0-9+.-]*:|//)#i', $value)) {
                    null === $namespace ? $element->removeAttribute($name) : $element->removeAttributeNS($namespace, $name);
                    $removed = true;
                }
            }
        }

        return $removed ? (string) $document->saveXML() : $svg;
    }

    /**
     * The width and height in pixels, rounded: from the width and height attributes when they're absolute, and
     * otherwise from the view box. null when it has neither
     *
     * @return array{int, int}|null
     */
    public function dimensions(string $svg): ?array
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $document = new \DOMDocument();
            $loaded = $document->loadXML($svg, \LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $root = $document->documentElement;
        if (!$loaded || null === $root) {
            return null;
        }

        $width = self::pixels($root->getAttribute('width'));
        $height = self::pixels($root->getAttribute('height'));
        if (null !== $width && null !== $height) {
            return [$width, $height];
        }

        $viewBox = preg_split('/[\s,]+/', trim($root->getAttribute('viewBox')));
        if (false === $viewBox || 4 !== count($viewBox) || !is_numeric($viewBox[2]) || !is_numeric($viewBox[3])) {
            return null;
        }

        $width = (int) round((float) $viewBox[2]);
        $height = (int) round((float) $viewBox[3]);

        return $width > 0 && $height > 0 ? [$width, $height] : null;
    }

    private static function pixels(string $length): ?int
    {
        if (1 !== preg_match('/^\s*(\d+(?:\.\d+)?)\s*(?:px)?\s*$/', $length, $matches)) {
            return null;
        }

        $pixels = (int) round((float) $matches[1]);

        return $pixels > 0 ? $pixels : null;
    }
}
