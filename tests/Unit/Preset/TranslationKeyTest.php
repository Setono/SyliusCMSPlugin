<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Preset;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Preset\TranslationKey;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

/**
 * See "Preset file format" in docs/spec/layouts-and-presets.md
 */
final class TranslationKeyTest extends TestCase
{
    /**
     * @test
     */
    public function it_is_translated_in_its_domain(): void
    {
        self::assertSame('Banners', (new TranslationKey('app.preset_category.banners', 'app'))->trans(self::translator()));
        self::assertSame('Bannere', (new TranslationKey('app.preset_category.banners', 'app'))->trans(self::translator(), 'da_DK'));
    }

    /**
     * @test
     */
    public function it_gives_a_text_as_it_is_and_a_key_translated(): void
    {
        self::assertSame('Promo', TranslationKey::textOf('Promo', self::translator()));
        self::assertNull(TranslationKey::textOf(null, self::translator()));
        self::assertSame('Banners', TranslationKey::textOf(new TranslationKey('app.preset_category.banners', 'app'), self::translator()));
    }

    /**
     * @test
     */
    public function it_reads_a_translation_key_from_a_stored_value_with_its_domain_or_the_default_one(): void
    {
        self::assertEquals(new TranslationKey('app.hero.heading'), TranslationKey::fromValue(['$trans' => 'app.hero.heading']));
        self::assertEquals(new TranslationKey('app.button', 'app'), TranslationKey::fromValue((object) ['$trans' => 'app.button', '$domain' => 'app']));
        self::assertSame(['$trans' => 'app.hero.heading'], (new TranslationKey('app.hero.heading'))->toValue());
        self::assertSame(['$trans' => 'app.button', '$domain' => 'app'], (new TranslationKey('app.button', 'app'))->toValue());
    }

    /**
     * @test
     *
     * @dataProvider notTranslationKeys
     */
    public function it_reads_no_translation_key_from_a_value_that_is_not_one(mixed $value): void
    {
        self::assertNull(TranslationKey::fromValue($value));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function notTranslationKeys(): iterable
    {
        yield 'a text' => ['Winter sale'];
        yield 'a locale map' => [['en_US' => 'Winter sale']];
        yield 'another key beside it' => [['$trans' => 'app.hero.heading', 'en_US' => 'Winter sale']];
        yield 'an empty key' => [['$trans' => '']];
        yield 'a key that is not a string' => [['$trans' => ['app.hero.heading']]];
        yield 'an empty domain' => [['$trans' => 'app.hero.heading', '$domain' => '']];
        yield 'a domain that is not a string' => [['$trans' => 'app.hero.heading', '$domain' => 7]];
    }

    private static function translator(): Translator
    {
        $translator = new Translator('en_US');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['app.preset_category.banners' => 'Banners'], 'en_US', 'app');
        $translator->addResource('array', ['app.preset_category.banners' => 'Bannere'], 'da_DK', 'app');

        return $translator;
    }
}
