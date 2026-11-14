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
