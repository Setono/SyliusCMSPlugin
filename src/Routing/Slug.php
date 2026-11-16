<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Routing;

/**
 * The rules a slug follows. Editors may type slashes, such as about/team, but a slug can't be empty, start or end with
 * a slash, or contain two slashes in a row. See "Slugs" in docs/spec/urls-and-seo.md
 */
final class Slug
{
    public const PATTERN = '#^(?!/)(?!.*//)(?!.*/$).+$#';

    /**
     * PATTERN as an HTML pattern attribute, which the browser anchors itself
     */
    public const HTML_PATTERN = '(?!/)(?!.*//)(?!.*/$).+';

    public static function isValid(string $slug): bool
    {
        return 1 === preg_match(self::PATTERN, $slug);
    }
}
