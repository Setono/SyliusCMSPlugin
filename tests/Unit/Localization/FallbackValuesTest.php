<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Localization;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\RichText;
use Setono\SyliusCMSPlugin\Field\RichTextHandler;
use Setono\SyliusCMSPlugin\Field\Text;
use Setono\SyliusCMSPlugin\Field\TextHandler;
use Setono\SyliusCMSPlugin\Localization\FallbackChainInterface;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\RichText\RichTextSchema;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class FallbackValuesTest extends TestCase
{
    use ProphecyTrait;

    private const EMPTY_DOCUMENT = ['type' => 'doc', 'content' => [['type' => 'paragraph']]];

    /**
     * @test
     *
     * @dataProvider provideMissingValues
     */
    public function it_counts_an_empty_value_as_missing(mixed $value): void
    {
        self::assertTrue($this->values()->isMissing(new RichText(label: 'Content'), $value));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function provideMissingValues(): iterable
    {
        yield 'null' => [null];
        yield 'an empty string' => [''];
        yield 'an empty list' => [[]];
        yield 'what the field type counts as empty' => [self::EMPTY_DOCUMENT];
    }

    /**
     * @test
     *
     * @dataProvider provideValues
     */
    public function it_counts_any_other_value_as_there(mixed $value): void
    {
        self::assertFalse($this->values()->isMissing(new Text(label: 'Text'), $value));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function provideValues(): iterable
    {
        yield 'text' => ['Hello'];
        yield 'zero' => [0];
        yield 'the string zero' => ['0'];
        yield 'false' => [false];
        yield 'a list' => [['Hello']];
    }

    /**
     * @test
     */
    public function it_gives_a_translatable_values_in_the_order_the_fallback_chain_reaches_them_past_missing_ones(): void
    {
        $values = $this->values()->inFallbackOrder(new Text(label: 'Text'), ['de_CH' => '', 'fr_FR' => [], 'de_DE' => 'Hallo', 'en_US' => 'Hello', 'da_DK' => 'Hej'], 'de_CH');

        self::assertSame(['de_DE' => 'Hallo', 'en_US' => 'Hello'], iterator_to_array($values));
    }

    /**
     * @test
     */
    public function it_gives_no_values_for_a_stored_value_that_is_not_a_locale_map(): void
    {
        self::assertSame([], iterator_to_array($this->values()->inFallbackOrder(new Text(label: 'Text'), 'Hello', 'de_CH')));
    }

    private function values(): FallbackValues
    {
        $fallbackChain = $this->prophesize(FallbackChainInterface::class);
        $fallbackChain->localesFor('de_CH')->willReturn(['de_CH', 'fr_FR', 'de_DE', 'en_US']);

        return new FallbackValues(
            new FieldHandlerLocator(new ServiceLocator([
                TextHandler::class => static fn (): TextHandler => new TextHandler(),
                RichTextHandler::class => static fn (): RichTextHandler => new RichTextHandler(new RichTextSchema()),
            ])),
            $fallbackChain->reveal(),
        );
    }
}
