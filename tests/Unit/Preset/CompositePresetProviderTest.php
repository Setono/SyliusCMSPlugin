<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Preset;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Preset\CompositePresetProvider;
use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Preset\PresetProviderInterface;

final class CompositePresetProviderTest extends TestCase
{
    use ProphecyTrait;

    private const UUID = '0193a5be-7255-7f00-8a1b-2c3d4e5f6a7b';

    /**
     * @test
     */
    public function it_lists_the_presets_of_every_provider_in_their_order_and_finds_one_in_whichever_has_it(): void
    {
        $hero = new Preset('Hero', [], code: 'hero');
        $promo = new Preset('Promo', [], uuid: self::UUID, changedAt: new \DateTimeImmutable());
        $code = $this->prophesize(PresetProviderInterface::class);
        $code->all()->willReturn([$hero]);
        $code->find('hero')->willReturn($hero);
        $code->find(self::UUID)->willReturn(null);
        $code->find('banner')->willReturn(null);
        $editor = $this->prophesize(PresetProviderInterface::class);
        $editor->all()->willReturn([$promo]);
        $editor->find(self::UUID)->willReturn($promo);
        $editor->find('banner')->willReturn(null);

        $provider = new CompositePresetProvider([$code->reveal(), $editor->reveal()]);

        self::assertSame([$hero, $promo], $provider->all());
        self::assertSame($hero, $provider->find('hero'));
        self::assertSame($promo, $provider->find(self::UUID));
        self::assertNull($provider->find('banner'));
    }
}
