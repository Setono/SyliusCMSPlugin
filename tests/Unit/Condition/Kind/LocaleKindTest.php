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
    public function it_tells_whether_a_locale_condition_among_a_blocks_conditions_hides_it_in_a_locale(): void
    {
        $notDanish = ['kind' => 'locale', 'negate' => true, 'params' => ['locales' => ['da_DK']]];
        $danishOrGerman = ['kind' => 'locale', 'negate' => false, 'params' => ['locales' => ['da_DK', 'de_DE']]];
        $channel = ['kind' => 'channel', 'negate' => false, 'params' => ['locales' => []]];

        self::assertTrue(LocaleKind::hidesIn([$notDanish], 'da_DK'));
        self::assertFalse(LocaleKind::hidesIn([$notDanish], 'en_US'));
        self::assertTrue(LocaleKind::hidesIn([$danishOrGerman], 'en_US'));
        self::assertFalse(LocaleKind::hidesIn([$danishOrGerman], 'de_DE'));
        self::assertTrue(LocaleKind::hidesIn([$channel, $danishOrGerman], 'en_US'), 'Conditions of other kinds are skipped');
        self::assertFalse(LocaleKind::hidesIn([$channel], 'en_US'));
        self::assertFalse(LocaleKind::hidesIn([], 'en_US'));
    }

    /**
     * Without locales, "is one of" matches nobody and "is not one of" everybody, as when its parameters are hydrated
     *
     * @test
     */
    public function it_hides_a_block_with_a_locale_condition_without_locales_unless_negated(): void
    {
        self::assertTrue(LocaleKind::hidesIn([['kind' => 'locale', 'negate' => false, 'params' => []]], 'en_US'));
        self::assertTrue(LocaleKind::hidesIn([['kind' => 'locale', 'params' => ['locales' => 'en_US']]], 'en_US'));
        self::assertTrue(LocaleKind::hidesIn([['kind' => 'locale', 'negate' => 1, 'params' => new \stdClass()]], 'en_US'), 'Only a negate of true negates');
        self::assertFalse(LocaleKind::hidesIn([['kind' => 'locale', 'negate' => true, 'params' => new \stdClass()]], 'en_US'));
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
