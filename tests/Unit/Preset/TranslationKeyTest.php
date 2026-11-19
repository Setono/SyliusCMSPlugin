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

    private static function translator(): Translator
    {
        $translator = new Translator('en_US');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['app.preset_category.banners' => 'Banners'], 'en_US', 'app');
        $translator->addResource('array', ['app.preset_category.banners' => 'Bannere'], 'da_DK', 'app');

        return $translator;
    }
}
