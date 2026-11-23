<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Validation;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Validation\ValidationError;

final class ValidationErrorTest extends TestCase
{
    /**
     * @test
     */
    public function it_is_json_with_where_it_is_and_its_message(): void
    {
        self::assertSame(
            '{"block":"0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f","field":"text","locale":"en_US","message":"This field is required."}',
            json_encode(new ValidationError('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f', 'text', 'en_US', 'This field is required.'), \JSON_THROW_ON_ERROR),
        );
        self::assertSame(
            '{"block":null,"field":null,"locale":null,"message":"Needs at least one Heading block inside."}',
            json_encode(new ValidationError(null, null, null, 'Needs at least one Heading block inside.'), \JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @test
     */
    public function it_is_json_saying_which_of_the_blocks_visibility_conditions_it_is_in_from_0(): void
    {
        self::assertSame(
            '{"block":"0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f","field":"until","locale":null,"message":"Until must come after From.","condition":1}',
            json_encode(new ValidationError('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f', 'until', null, 'Until must come after From.', condition: 1), \JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @test
     */
    public function it_is_json_saying_it_is_in_one_of_the_layouts_fields(): void
    {
        self::assertSame(
            '{"block":null,"field":"headline","locale":"en_US","message":"This field is required.","layout":true}',
            json_encode(new ValidationError(null, 'headline', 'en_US', 'This field is required.', inLayout: true), \JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @test
     */
    public function it_moves_an_error_into_a_blocks_visibility_condition_or_into_the_layout_keeping_its_field_locale_and_message(): void
    {
        $error = new ValidationError(null, 'until', 'en_US', 'Until must come after From.');

        self::assertEquals(new ValidationError('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f', 'until', 'en_US', 'Until must come after From.', condition: 2), $error->inCondition('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f', 2));
        self::assertEquals(new ValidationError(null, 'until', 'en_US', 'Until must come after From.', inLayout: true), $error->inLayout());
    }

    /**
     * @test
     */
    public function it_takes_a_message_that_is_stringable_as_a_constraint_violations_can_be(): void
    {
        $message = new class() implements \Stringable {
            public function __toString(): string
            {
                return 'This value should not be blank.';
            }
        };

        self::assertSame('This value should not be blank.', (new ValidationError(null, 'campaign', null, $message))->message);
    }
}
