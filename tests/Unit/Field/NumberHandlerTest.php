<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Field;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Field\HydrationContext;
use Setono\SyliusCMSPlugin\Field\Number;
use Setono\SyliusCMSPlugin\Field\NumberHandler;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Tests\Unit\Field\Fixture\WithNumber;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

final class NumberHandlerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_is_the_shipped_number_field_type_shared_by_all_translations_by_default(): void
    {
        $fields = (new FieldReader())->read(WithNumber::class);

        self::assertSame('number', $fields['interval']->type->code);
        self::assertFalse($fields['interval']->translatable);
        self::assertSame(5, $fields['interval']->default);
        self::assertSame(NumberHandler::class, $fields['interval']->handler());
    }

    /**
     * @test
     */
    public function its_schema_is_a_number_with_its_own_options(): void
    {
        self::assertSame(
            ['type' => 'number', 'x-cms-options' => ['min' => null, 'max' => null, 'step' => null, 'integer' => false]],
            (new NumberHandler())->schema(new Number(label: 'Rating')),
        );
    }

    /**
     * @test
     */
    public function its_schema_is_an_integer_between_its_bounds_when_the_field_takes_whole_numbers(): void
    {
        self::assertSame(
            [
                'type' => 'integer',
                'minimum' => 2,
                'maximum' => 30,
                'x-cms-options' => ['min' => 2, 'max' => 30, 'step' => 2, 'integer' => true],
            ],
            (new NumberHandler())->schema(new Number(label: 'Interval', min: 2, max: 30, step: 2, integer: true)),
        );
    }

    /**
     * @test
     */
    public function it_hydrates_a_number_as_it_is(): void
    {
        $handler = new NumberHandler();

        self::assertSame(5, $handler->hydrate(new Number(label: 'Rating'), 5, $this->hydrationContext()));
        self::assertSame(4.5, $handler->hydrate(new Number(label: 'Rating'), 4.5, $this->hydrationContext()));
    }

    /**
     * JSON doesn't tell 5 from 5.0, so a whole number stored as a float is an int in a field that takes whole numbers
     *
     * @test
     */
    public function it_hydrates_a_whole_number_as_an_int_when_the_field_takes_whole_numbers(): void
    {
        self::assertSame(5, (new NumberHandler())->hydrate(new Number(label: 'Interval', integer: true), 5.0, $this->hydrationContext()));
    }

    /**
     * @test
     */
    public function it_hydrates_a_value_that_does_not_fit_as_missing(): void
    {
        $handler = new NumberHandler();

        self::assertNull($handler->hydrate(new Number(label: 'Rating'), '5', $this->hydrationContext()));
        self::assertNull($handler->hydrate(new Number(label: 'Rating'), true, $this->hydrationContext()));
        self::assertNull($handler->hydrate(new Number(label: 'Interval', integer: true), 4.5, $this->hydrationContext()));
    }

    /**
     * A number out of the field's bounds still fits, and renders: publishing refuses it
     *
     * @test
     */
    public function it_fits_a_number_and_in_a_field_that_takes_whole_numbers_only_a_whole_one(): void
    {
        $handler = new NumberHandler();

        self::assertTrue($handler->fits(new Number(label: 'Rating'), 5));
        self::assertTrue($handler->fits(new Number(label: 'Rating'), -4.5));
        self::assertTrue($handler->fits(new Number(label: 'Interval', min: 2, max: 30, integer: true), 60));
        self::assertTrue($handler->fits(new Number(label: 'Interval', integer: true), 5.0));
        self::assertFalse($handler->fits(new Number(label: 'Interval', integer: true), 4.5));
        self::assertFalse($handler->fits(new Number(label: 'Rating'), '5'));
        self::assertFalse($handler->fits(new Number(label: 'Rating'), false));
        self::assertFalse($handler->fits(new Number(label: 'Rating'), null));
    }

    /**
     * @test
     */
    public function it_holds_no_references(): void
    {
        self::assertSame([], [...(new NumberHandler())->references(new Number(label: 'Rating'), 5)]);
    }

    /**
     * @test
     */
    public function it_accepts_a_missing_value_and_a_number_between_the_bounds_on_a_step(): void
    {
        $context = $this->prophesize(ExecutionContextInterface::class);
        $context->buildViolation(Argument::cetera())->shouldNotBeCalled();

        $handler = new NumberHandler();
        $field = new Number(label: 'Interval', min: 2, max: 30, step: 2, integer: true);
        $handler->validate($field, null, $context->reveal());
        $handler->validate($field, 2, $context->reveal());
        $handler->validate($field, 16, $context->reveal());
        $handler->validate($field, 30, $context->reveal());
        $handler->validate($field, 16.0, $context->reveal());
        $handler->validate(new Number(label: 'Rating'), -4.75, $context->reveal());
        // Steps count from the lowest value, as in an HTML number input, and floats are compared with a tolerance
        $handler->validate(new Number(label: 'Price', min: 0.1, step: 0.1), 0.3, $context->reveal());
        $handler->validate(new Number(label: 'Odd', min: 1, step: 2), 7, $context->reveal());
    }

    /**
     * @test
     */
    public function it_rejects_a_value_that_is_not_a_number(): void
    {
        $this->expectViolation('setono_sylius_cms.field.not_number', [], '5');
        $this->expectViolation('setono_sylius_cms.field.not_number', [], true);
    }

    /**
     * @test
     */
    public function it_rejects_a_fraction_when_the_field_takes_whole_numbers(): void
    {
        $this->expectViolation('setono_sylius_cms.field.not_whole_number', [], 4.5, new Number(label: 'Interval', integer: true));
    }

    /**
     * @test
     */
    public function it_rejects_a_number_below_the_lowest(): void
    {
        $this->expectViolation('setono_sylius_cms.field.too_small', ['{{ limit }}' => '2'], 1, new Number(label: 'Interval', min: 2, max: 30));
    }

    /**
     * @test
     */
    public function it_rejects_a_number_above_the_highest(): void
    {
        $this->expectViolation('setono_sylius_cms.field.too_large', ['{{ limit }}' => '30'], 31, new Number(label: 'Interval', min: 2, max: 30));
    }

    /**
     * @test
     */
    public function it_rejects_a_number_off_the_steps_and_names_the_two_nearest_that_are_on_them(): void
    {
        $this->expectViolation('setono_sylius_cms.field.not_on_step', ['{{ lower }}' => '4', '{{ upper }}' => '6'], 5, new Number(label: 'Even', step: 2));
        $this->expectViolation('setono_sylius_cms.field.not_on_step', ['{{ lower }}' => '0.3', '{{ upper }}' => '0.4'], 0.35, new Number(label: 'Price', min: 0.1, step: 0.1));
        $this->expectViolation('setono_sylius_cms.field.not_on_step', ['{{ lower }}' => '5', '{{ upper }}' => '7'], 6, new Number(label: 'Odd', min: 1, step: 2));
    }

    /**
     * @test
     */
    public function it_needs_a_step_above_zero(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Number(label: 'Interval', step: 0);
    }

    /**
     * @test
     */
    public function it_needs_its_lowest_value_to_be_no_higher_than_its_highest(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Number(label: 'Interval', min: 30, max: 2);
    }

    /**
     * @param array<string, string> $parameters
     */
    private function expectViolation(string $message, array $parameters, mixed $value, ?Number $field = null): void
    {
        $builder = $this->prophesize(ConstraintViolationBuilderInterface::class);
        foreach ($parameters as $name => $parameter) {
            $builder->setParameter($name, $parameter)->willReturn($builder->reveal())->shouldBeCalledOnce();
        }
        $builder->addViolation()->shouldBeCalledOnce();

        $context = $this->prophesize(ExecutionContextInterface::class);
        $context->buildViolation($message)->willReturn($builder->reveal())->shouldBeCalledOnce();

        (new NumberHandler())->validate($field ?? new Number(label: 'Rating'), $value, $context->reveal());
    }

    private function hydrationContext(): HydrationContext
    {
        return new HydrationContext(new RenderContext(
            $this->prophesize(ChannelInterface::class)->reveal(),
            'en_US',
            'USD',
            null,
            '@guest',
            new \DateTimeImmutable(),
        ));
    }
}
