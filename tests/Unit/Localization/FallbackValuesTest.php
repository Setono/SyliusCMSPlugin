<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Localization;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Field\Field;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\RichText;
use Setono\SyliusCMSPlugin\Field\RichTextHandler;
use Setono\SyliusCMSPlugin\Field\Text;
use Setono\SyliusCMSPlugin\Field\Textarea;
use Setono\SyliusCMSPlugin\Field\TextHandler;
use Setono\SyliusCMSPlugin\Localization\FallbackChainInterface;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Tests\Support\Links;
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

    /**
     * An empty string, an empty rich text document and a missing locale all fall back. See "Fallback chain" in
     * docs/spec/localization.md
     *
     * @test
     *
     * @dataProvider provideEmptyValuesPerFieldType
     */
    public function it_falls_back_past_a_value_that_is_empty_for_its_field_type(Field $field, mixed $empty, mixed $fallback): void
    {
        $stored = ['de_CH' => $empty, 'de_DE' => $fallback, 'en_US' => $fallback];

        self::assertSame(['de_DE' => $fallback, 'en_US' => $fallback], iterator_to_array($this->values()->inFallbackOrder($field, $stored, 'de_CH')));
        self::assertSame(['de_DE' => $fallback, 'en_US' => $fallback], iterator_to_array($this->values()->inFallbackOrder($field, array_diff_key($stored, ['de_CH' => true]), 'de_CH')));
    }

    /**
     * @return iterable<string, array{Field, mixed, mixed}>
     */
    public static function provideEmptyValuesPerFieldType(): iterable
    {
        yield 'text' => [new Text(label: 'Text'), '', 'Hallo'];
        yield 'a textarea' => [new Textarea(label: 'Text'), '', "Hallo\nWelt"];
        yield 'rich text' => [new RichText(label: 'Content'), self::EMPTY_DOCUMENT, ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hallo']]]]]];
        yield 'rich text with only whitespace and a line break' => [new RichText(label: 'Content'), ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => ' '], ['type' => 'hardBreak']]]]], ['type' => 'doc', 'content' => [['type' => 'horizontalRule']]]];
    }

    /**
     * Entry fields fall back the same way as block fields. See "Fallback chain" in docs/spec/localization.md
     *
     * @test
     */
    public function it_gives_an_entry_fields_value_in_the_locale_or_the_first_one_the_fallback_chain_reaches(): void
    {
        $page = self::page(['de_CH' => 'Über uns (CH)', 'fr_FR' => '', 'de_DE' => 'Über uns', 'en_US' => 'About us']);

        self::assertSame('Über uns (CH)', $this->values()->entryValue($page, 'title', 'de_CH'));

        $page->getTranslation('de_CH')->setTitle(null);
        self::assertSame('Über uns', $this->values()->entryValue($page, 'title', 'de_CH'), 'The empty French title falls back too');

        $page->removeTranslation($page->getTranslation('de_DE'));
        self::assertSame('About us', $this->values()->entryValue($page, 'title', 'de_CH'));

        $page->getTranslation('en_US')->setTitle('');
        self::assertNull($this->values()->entryValue($page, 'title', 'de_CH'));
    }

    /**
     * @test
     */
    public function it_refuses_an_entry_field_the_translations_do_not_have(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The translations of Setono\SyliusCMSPlugin\Model\Page have no field "subtitle"');

        $this->values()->entryValue(self::page(['en_US' => 'About us']), 'subtitle', 'de_CH');
    }

    /**
     * A URL exists only in locales with a slug
     *
     * @test
     */
    public function it_never_falls_back_for_a_slug(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Slugs never fall back');

        $this->values()->entryValue(self::page(['en_US' => 'About us']), 'slug', 'de_CH');
    }

    /**
     * @param array<string, string> $titles by locale
     */
    private static function page(array $titles): Page
    {
        $page = new Page();
        foreach ($titles as $locale => $title) {
            $translation = new PageTranslation();
            $translation->setLocale($locale);
            $translation->setTitle($title);
            $page->addTranslation($translation);
        }

        return $page;
    }

    private function values(): FallbackValues
    {
        $fallbackChain = $this->prophesize(FallbackChainInterface::class);
        $fallbackChain->localesFor('de_CH')->willReturn(['de_CH', 'fr_FR', 'de_DE', 'en_US']);

        return new FallbackValues(
            new FieldHandlerLocator(new ServiceLocator([
                TextHandler::class => static fn (): TextHandler => new TextHandler(),
                RichTextHandler::class => static fn (): RichTextHandler => Links::richTextHandler(),
            ])),
            $fallbackChain->reveal(),
        );
    }
}
