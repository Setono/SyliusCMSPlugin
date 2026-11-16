<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Json;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Json\SchemaType;

final class SchemaTypeTest extends TestCase
{
    /**
     * @test
     *
     * @dataProvider provideAllowedValues
     *
     * @param array<string, mixed> $schema
     */
    public function it_allows_a_value_of_a_json_type_the_schema_names(array $schema, mixed $value): void
    {
        self::assertTrue(SchemaType::allows($schema, $value));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, mixed}>
     */
    public function provideAllowedValues(): iterable
    {
        yield 'a string' => [['type' => 'string'], 'Hello'];
        yield 'an integer as a number' => [['type' => 'number'], 3];
        yield 'a float as a number' => [['type' => 'number'], 2.5];
        yield 'an integer' => [['type' => 'integer'], 3];
        yield 'a boolean' => [['type' => 'boolean'], false];
        yield 'null' => [['type' => 'null'], null];
        yield 'a list as an array' => [['type' => 'array'], ['a', 'b']];
        yield 'a map as an object' => [['type' => 'object'], ['mon' => '9-17']];
        yield 'one of several types' => [['type' => ['string', 'number']], 3];
        yield 'anything, when the schema names no type' => [['enum' => ['a']], 42];
    }

    /**
     * @test
     *
     * @dataProvider provideRefusedValues
     *
     * @param array<string, mixed> $schema
     */
    public function it_refuses_a_value_of_another_json_type(array $schema, mixed $value): void
    {
        self::assertFalse(SchemaType::allows($schema, $value));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, mixed}>
     */
    public function provideRefusedValues(): iterable
    {
        yield 'a number as a string' => [['type' => 'string'], 3];
        yield 'a string as a number' => [['type' => 'number'], '3'];
        yield 'a float as an integer' => [['type' => 'integer'], 2.5];
        yield 'a string as a boolean' => [['type' => 'boolean'], 'true'];
        yield 'false as null' => [['type' => 'null'], false];
        yield 'a map as an array' => [['type' => 'array'], ['a' => 'b']];
        yield 'a string as an array' => [['type' => 'array'], 'a'];
        yield 'a list as an object' => [['type' => 'object'], ['a', 'b']];
        yield 'a string as an object' => [['type' => 'object'], 'always'];
        yield 'none of several types' => [['type' => ['string', 'number']], true];
        yield 'a type that is no JSON type' => [['type' => 'date'], '2026-11-16'];
    }
}
