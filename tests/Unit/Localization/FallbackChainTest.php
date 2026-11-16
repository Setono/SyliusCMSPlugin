<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Localization;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Localization\FallbackChain;
use Sylius\Resource\Translation\Provider\TranslationLocaleProviderInterface;

/**
 * See "Fallback chain" in docs/spec/localization.md
 */
final class FallbackChainTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_falls_back_from_a_locale_to_the_store_fallback_locale(): void
    {
        self::assertSame(['da_DK', 'en_US'], $this->chain()->localesFor('da_DK'));
    }

    /**
     * @test
     */
    public function it_lists_the_store_fallback_locale_once(): void
    {
        self::assertSame(['en_US'], $this->chain()->localesFor('en_US'));
    }

    /**
     * @test
     */
    public function it_falls_back_through_the_configured_locales_in_order_before_the_store_fallback_locale(): void
    {
        $chain = $this->chain(['de_CH' => ['de_DE'], 'fr_CH' => ['fr_FR', 'de_DE']]);

        self::assertSame(['de_CH', 'de_DE', 'en_US'], $chain->localesFor('de_CH'));
        self::assertSame(['fr_CH', 'fr_FR', 'de_DE', 'en_US'], $chain->localesFor('fr_CH'));
    }

    /**
     * The configured locales are this locale's own: de_DE's chain doesn't continue through what de_CH falls back to
     *
     * @test
     */
    public function it_falls_back_only_through_the_locales_configured_for_the_locale_itself(): void
    {
        $chain = $this->chain(['de_CH' => ['de_DE'], 'de_DE' => ['de_AT']]);

        self::assertSame(['de_CH', 'de_DE', 'en_US'], $chain->localesFor('de_CH'));
    }

    /**
     * @test
     */
    public function it_lists_each_locale_once_where_the_chain_first_reaches_it(): void
    {
        $chain = $this->chain(['de_CH' => ['de_DE', 'de_CH', 'de_DE', 'fr_FR']]);

        self::assertSame(['de_CH', 'de_DE', 'fr_FR', 'en_US'], $chain->localesFor('de_CH'));
    }

    /**
     * @param array<string, list<string>> $fallbackLocales
     */
    private function chain(array $fallbackLocales = []): FallbackChain
    {
        $provider = $this->prophesize(TranslationLocaleProviderInterface::class);
        $provider->getDefaultLocaleCode()->willReturn('en_US');

        return new FallbackChain($provider->reveal(), $fallbackLocales);
    }
}
