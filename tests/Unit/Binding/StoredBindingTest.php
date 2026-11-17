<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Binding;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Binding\StoredBinding;

/**
 * What a bound field stores: {"$bind": "<context key>.<property code>", "$params": {…}, "$fallback": …}. See "Field
 * values" in docs/spec/content-model.md
 */
final class StoredBindingTest extends TestCase
{
    /**
     * @test
     */
    public function it_reads_a_binding_with_its_parameters_and_fallback(): void
    {
        $binding = StoredBinding::fromValue(['$bind' => 'product.attribute', '$params' => ['attribute' => 'material'], '$fallback' => ['en_US' => 'cotton']]);

        self::assertNotNull($binding);
        self::assertSame('product.attribute', $binding->property);
        self::assertSame('product', $binding->contextKey());
        self::assertSame('attribute', $binding->code());
        self::assertSame(['attribute' => 'material'], $binding->params);
        self::assertSame(['en_US' => 'cotton'], $binding->fallback);
    }

    /**
     * Canonical JSON decodes an empty map as a stdClass
     *
     * @test
     */
    public function it_reads_parameters_and_a_fallback_stored_as_an_empty_map(): void
    {
        $binding = StoredBinding::fromValue(['$bind' => 'customer.first_name', '$params' => new \stdClass(), '$fallback' => new \stdClass()]);

        self::assertNotNull($binding);
        self::assertSame([], $binding->params);
        self::assertSame([], $binding->fallback);
    }

    /**
     * @test
     */
    public function it_tells_a_value_meant_as_a_binding_whatever_its_shape(): void
    {
        self::assertTrue(StoredBinding::isBinding(['$bind' => 'product.name']));
        self::assertFalse(StoredBinding::isBinding(['en_US' => 'Hi']));
        self::assertFalse(StoredBinding::isBinding('product.name'));
    }

    /**
     * @test
     *
     * @dataProvider valuesThatAreNoBinding
     */
    public function it_reads_no_binding_from_a_value_of_another_shape(mixed $value): void
    {
        self::assertNull(StoredBinding::fromValue($value));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function valuesThatAreNoBinding(): iterable
    {
        yield 'a string' => ['Hi'];
        yield 'a locale map' => [['en_US' => 'Hi']];
        yield 'no parameters' => [['$bind' => 'product.name', '$fallback' => null]];
        yield 'no fallback' => [['$bind' => 'product.name', '$params' => []]];
        yield 'another key' => [['$bind' => 'product.name', '$params' => [], '$fallback' => null, 'en_US' => 'Hi']];
        yield 'a property without a context key' => [['$bind' => 'name', '$params' => [], '$fallback' => null]];
        yield 'a property that is no code' => [['$bind' => 'product.Name', '$params' => [], '$fallback' => null]];
        yield 'parameters that are a list' => [['$bind' => 'product.name', '$params' => ['material'], '$fallback' => null]];
        yield 'a fallback that is a binding' => [['$bind' => 'product.name', '$params' => [], '$fallback' => ['$bind' => 'taxon.name', '$params' => [], '$fallback' => null]]];
    }

    /**
     * @test
     */
    public function it_gives_the_stored_shape(): void
    {
        self::assertSame(
            ['$bind' => 'product.name', '$params' => [], '$fallback' => 'this product'],
            (new StoredBinding('product.name', [], 'this product'))->toArray(),
        );
    }
}
