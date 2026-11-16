<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Condition\Kind;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Attribute\NoParams;
use Setono\SyliusCMSPlugin\Condition\Kind\LocaleKind;
use Setono\SyliusCMSPlugin\Condition\Kind\LocaleParams;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Sylius\Component\Core\Model\ChannelInterface;

final class LocaleKindTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_matches_when_the_shoppers_locale_is_one_of_the_locales(): void
    {
        $params = new LocaleParams();
        $params->locales = ['da_DK', 'de_DE'];

        self::assertTrue((new LocaleKind())->matches($params, $this->context('da_DK')));
        self::assertTrue((new LocaleKind())->matches($params, $this->context('de_DE')));
        self::assertFalse((new LocaleKind())->matches($params, $this->context('en_US')));
        self::assertFalse((new LocaleKind())->matches(new LocaleParams(), $this->context('en_US')));
    }

    /**
     * @test
     */
    public function it_takes_only_its_own_parameters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('The locale condition takes %s, got %s', LocaleParams::class, NoParams::class));

        (new LocaleKind())->matches(new NoParams(), $this->context('en_US'));
    }

    private function context(string $localeCode): RenderContext
    {
        return new RenderContext($this->prophesize(ChannelInterface::class)->reveal(), $localeCode, 'USD', null, '@guest', new \DateTimeImmutable());
    }
}
