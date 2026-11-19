<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Preset;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Preset\TranslationKey;
use Setono\SyliusCMSPlugin\Preset\TranslationKeyResolver;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

/**
 * On insert, each of the entry's locales gets a translation key's translation if the translator has one, and the
 * others stay empty, so they count as untranslated. See "What inserting copies" in docs/spec/layouts-and-presets.md
 */
final class TranslationKeyResolverTest extends TestCase
{
    /**
     * @test
     */
    public function it_resolves_a_key_in_a_locale_whose_catalogue_or_language_has_it(): void
    {
        $resolver = new TranslationKeyResolver(self::translator());

        self::assertSame('Winter sale', $resolver->resolve(new TranslationKey('app.hero'), 'en_US'));
        self::assertSame('Winter sale!', $resolver->resolve(new TranslationKey('app.hero'), 'en_GB'));
        self::assertSame('Vinterudsalg', $resolver->resolve(new TranslationKey('app.hero'), 'da_DK'));
    }

    /**
     * @test
     */
    public function it_resolves_nothing_in_a_locale_that_only_falls_back_to_the_store_s_fallback_locale(): void
    {
        self::assertNull((new TranslationKeyResolver(self::translator()))->resolve(new TranslationKey('app.hero'), 'de_DE'));
    }

    /**
     * @test
     */
    public function it_resolves_a_key_in_its_own_domain_only(): void
    {
        $resolver = new TranslationKeyResolver(self::translator());

        self::assertSame('Banners', $resolver->resolve(new TranslationKey('app.banners', 'cms'), 'en_US'));
        self::assertNull($resolver->resolve(new TranslationKey('app.banners'), 'en_US'));
        self::assertNull($resolver->resolve(new TranslationKey('app.unknown'), 'en_US'));
    }

    private static function translator(): Translator
    {
        $translator = new Translator('en_US');
        $translator->setFallbackLocales(['en']);
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['app.hero' => 'Winter sale'], 'en');
        $translator->addResource('array', ['app.hero' => 'Winter sale!'], 'en_GB');
        $translator->addResource('array', ['app.hero' => 'Vinterudsalg'], 'da');
        $translator->addResource('array', ['app.banners' => 'Banners'], 'en', 'cms');

        return $translator;
    }
}
