<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Field;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Field\Date;
use Setono\SyliusCMSPlugin\Field\DateHandler;
use Setono\SyliusCMSPlugin\Field\HydrationContext;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Tests\Unit\Field\Fixture\WithDates;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

final class DateHandlerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_is_the_shipped_date_field_type_shared_by_all_translations_by_default(): void
    {
        $fields = (new FieldReader())->read(WithDates::class);

        self::assertSame('date', $fields['day']->type->code);
        self::assertFalse($fields['day']->translatable);
        self::assertSame(DateHandler::class, $fields['day']->handler());
    }

    /**
     * @test
     */
    public function it_describes_a_date_string_in_its_schema(): void
    {
        self::assertSame(['type' => 'string', 'format' => 'date'], (new DateHandler())->schema(new Date(label: 'Day')));
    }

    /**
     * @test
     */
    public function it_hydrates_a_day_at_midnight_utc(): void
    {
        $day = (new DateHandler())->hydrate(new Date(label: 'Day'), '2026-11-13', $this->hydrationContext());

        self::assertInstanceOf(\DateTimeImmutable::class, $day);
        self::assertSame('2026-11-13T00:00:00+00:00', $day->format(\DATE_ATOM));
        self::assertSame('UTC', $day->getTimezone()->getName());
    }

    /**
     * @test
     *
     * @dataProvider provideValuesThatDoNotFit
     */
    public function it_hydrates_a_value_that_does_not_fit_as_missing(mixed $value): void
    {
        self::assertNull((new DateHandler())->hydrate(new Date(label: 'Day'), $value, $this->hydrationContext()));
        self::assertFalse((new DateHandler())->fits(new Date(label: 'Day'), $value));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public function provideValuesThatDoNotFit(): iterable
    {
        yield 'a day that does not exist' => ['2026-02-30'];
        yield 'a date and time' => ['2026-11-13T09:30:00Z'];
        yield 'another format' => ['13/11/2026'];
        yield 'with a trailing space' => ['2026-11-13 '];
        yield 'a number' => [20261113];
        yield 'nothing' => [null];
    }

    /**
     * @test
     */
    public function it_fits_a_day_written_as_the_stored_format_writes_it(): void
    {
        self::assertTrue((new DateHandler())->fits(new Date(label: 'Day'), '2026-11-13'));
        self::assertTrue((new DateHandler())->fits(new Date(label: 'Day'), '2028-02-29'));
    }

    /**
     * @test
     */
    public function it_holds_no_references(): void
    {
        self::assertSame([], [...(new DateHandler())->references(new Date(label: 'Day'), '2026-11-13')]);
    }

    /**
     * @test
     */
    public function it_accepts_a_day_and_a_missing_value(): void
    {
        $context = $this->prophesize(ExecutionContextInterface::class);
        $context->buildViolation(Argument::cetera())->shouldNotBeCalled();

        (new DateHandler())->validate(new Date(label: 'Day'), '2026-11-13', $context->reveal());
        (new DateHandler())->validate(new Date(label: 'Day'), null, $context->reveal());
    }

    /**
     * @test
     */
    public function it_rejects_a_value_that_is_not_a_day(): void
    {
        $builder = $this->prophesize(ConstraintViolationBuilderInterface::class);
        $builder->addViolation()->shouldBeCalledOnce();

        $context = $this->prophesize(ExecutionContextInterface::class);
        $context->buildViolation('setono_sylius_cms.field.not_date')->willReturn($builder->reveal())->shouldBeCalledOnce();

        (new DateHandler())->validate(new Date(label: 'Day'), '2026-13-01', $context->reveal());
    }

    private function hydrationContext(): HydrationContext
    {
        return new HydrationContext(new RenderContext($this->prophesize(ChannelInterface::class)->reveal(), 'en_US', 'USD', null, '@guest', new \DateTimeImmutable()));
    }
}
