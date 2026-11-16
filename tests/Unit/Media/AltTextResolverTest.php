<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Media;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Localization\FallbackChainInterface;
use Setono\SyliusCMSPlugin\Media\AltTextResolver;
use Setono\SyliusCMSPlugin\Model\MediaItem;

/**
 * Alt text is translatable, and falls back through the fallback chain. See "Metadata" in docs/spec/media.md
 */
final class AltTextResolverTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_gives_the_alt_text_in_the_locale(): void
    {
        $mediaItem = new MediaItem();
        $mediaItem->setAltText('de_CH', 'Ein Hund');
        $mediaItem->setAltText('en_US', 'A dog');

        self::assertSame('Ein Hund', $this->resolver()->resolve($mediaItem, 'de_CH'));
    }

    /**
     * @test
     */
    public function it_falls_back_through_the_chain_in_order(): void
    {
        $mediaItem = new MediaItem();
        $mediaItem->setAltText('de_DE', 'Ein Hund');
        $mediaItem->setAltText('en_US', 'A dog');

        self::assertSame('Ein Hund', $this->resolver()->resolve($mediaItem, 'de_CH'));

        $mediaItem->setAltText('de_DE', '');
        self::assertSame('A dog', $this->resolver()->resolve($mediaItem, 'de_CH'));
    }

    /**
     * @test
     */
    public function it_has_none_when_no_locale_of_the_chain_has_any(): void
    {
        $mediaItem = new MediaItem();
        $mediaItem->setAltText('fr_FR', 'Un chien');

        self::assertNull($this->resolver()->resolve($mediaItem, 'de_CH'));
    }

    private function resolver(): AltTextResolver
    {
        $fallbackChain = $this->prophesize(FallbackChainInterface::class);
        $fallbackChain->localesFor('de_CH')->willReturn(['de_CH', 'de_DE', 'en_US']);

        return new AltTextResolver($fallbackChain->reveal());
    }
}
