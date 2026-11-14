<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Localization;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Localization\FallbackChain;
use Sylius\Resource\Translation\Provider\TranslationLocaleProviderInterface;

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

    private function chain(): FallbackChain
    {
        $provider = $this->prophesize(TranslationLocaleProviderInterface::class);
        $provider->getDefaultLocaleCode()->willReturn('en_US');

        return new FallbackChain($provider->reveal());
    }
}
