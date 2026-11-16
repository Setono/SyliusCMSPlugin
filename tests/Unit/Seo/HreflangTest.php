<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Seo;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Seo\Hreflang;

/**
 * Hreflang values use the locale code with a hyphen. See "Head tags" in docs/spec/urls-and-seo.md
 */
final class HreflangTest extends TestCase
{
    /**
     * @test
     *
     * @dataProvider provideLocaleCodes
     */
    public function it_writes_a_locale_code_with_hyphens(string $localeCode, string $hreflang): void
    {
        self::assertSame($hreflang, Hreflang::of($localeCode));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideLocaleCodes(): iterable
    {
        yield 'a language and a region' => ['en_US', 'en-US'];
        yield 'another' => ['da_DK', 'da-DK'];
        yield 'a language alone' => ['de', 'de'];
        yield 'a language, a script and a region' => ['zh_Hant_TW', 'zh-Hant-TW'];
        yield 'a numeric region' => ['es_419', 'es-419'];
    }
}
