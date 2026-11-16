<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Localization;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Localization\Availability;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;

/**
 * An entry shows only in locales where it has a translation that isn't held back. See "Availability" in
 * docs/spec/localization.md
 */
final class AvailabilityTest extends TestCase
{
    /**
     * @test
     */
    public function it_counts_an_entrys_translation_in_a_locale_once_that_translation_has_a_title(): void
    {
        $page = self::page(['en_US' => 'About us', 'da_DK' => null, 'sv_SE' => '']);
        $availability = new Availability();

        self::assertTrue($availability->hasTranslation($page, 'en_US'));
        self::assertFalse($availability->hasTranslation($page, 'da_DK'));
        self::assertFalse($availability->hasTranslation($page, 'sv_SE'));
        self::assertFalse($availability->hasTranslation($page, 'de_DE'));
    }

    /**
     * Whole entries never fall back to another locale, not even to the entry's fallback locale
     *
     * @test
     */
    public function it_does_not_fall_back_to_another_locale(): void
    {
        $page = self::page(['en_US' => 'About us']);
        $page->setFallbackLocale('en_US');
        $availability = new Availability();

        self::assertFalse($availability->hasTranslation($page, 'da_DK'));
        self::assertFalse($availability->showsIn($page, 'da_DK'));
    }

    /**
     * @test
     */
    public function it_shows_an_entry_in_a_locale_where_it_has_a_translation_that_is_not_held_back(): void
    {
        $page = self::page(['en_US' => 'About us', 'da_DK' => 'Om os', 'sv_SE' => null], heldBack: ['da_DK', 'sv_SE']);
        $availability = new Availability();

        self::assertTrue($availability->showsIn($page, 'en_US'));
        self::assertFalse($availability->showsIn($page, 'da_DK'));
        self::assertTrue($availability->hasTranslation($page, 'da_DK'));
        self::assertFalse($availability->showsIn($page, 'sv_SE'));
        self::assertFalse($availability->showsIn($page, 'de_DE'));
    }

    /**
     * @param array<string, string|null> $titles by locale
     * @param list<string> $heldBack
     */
    private static function page(array $titles, array $heldBack = []): Page
    {
        $page = new Page();
        foreach ($titles as $localeCode => $title) {
            $translation = new PageTranslation();
            $translation->setLocale($localeCode);
            $translation->setTitle($title);
            $translation->setShown(!in_array($localeCode, $heldBack, true));
            $page->addTranslation($translation);
        }

        return $page;
    }
}
