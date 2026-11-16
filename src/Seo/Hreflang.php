<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Seo;

/**
 * The hreflang value of a locale: its code with hyphens, such as en-US. See "Head tags" in docs/spec/urls-and-seo.md
 */
final class Hreflang
{
    /** The hreflang of the link to the channel's default locale */
    public const X_DEFAULT = 'x-default';

    public static function of(string $localeCode): string
    {
        return str_replace('_', '-', $localeCode);
    }
}
