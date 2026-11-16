<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Field;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Field\DateTime;
use Setono\SyliusCMSPlugin\Field\DateTimeHandler;
use Setono\SyliusCMSPlugin\Field\HydrationContext;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Tests\Unit\Field\Fixture\WithDates;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

final class DateTimeHandlerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_is_the_shipped_date_and_time_field_type_shared_by_all_translations_by_default(): void
    {
        $fields = (new FieldReader())->read(WithDates::class);

        self::assertSame('date_time', $fields['at']->type->code);
        self::assertFalse($fields['at']->translatable);
        self::assertSame(DateTimeHandler::class, $fields['at']->handler());
    }

    /**
     * @test
     */
    public function it_describes_a_date_and_time_string_in_its_schema(): void
    {
        self::assertSame(['type' => 'string', 'format' => 'date-time'], (new DateTimeHandler())->schema(new DateTime(label: 'At')));
    }

    /**
     * @test
     */
    public function it_hydrates_a_time_in_utc(): void
    {
        $at = (new DateTimeHandler())->hydrate(new DateTime(label: 'At'), '2026-11-13T09:30:15Z', $this->hydrationContext());

        self::assertInstanceOf(\DateTimeImmutable::class, $at);
        self::assertSame('2026-11-13T09:30:15+00:00', $at->format(\DATE_ATOM));
        self::assertSame('UTC', $at->getTimezone()->getName());
    }

    /**
     * @test
     *
     * @dataProvider provideValuesThatDoNotFit
     */
    public function it_hydrates_a_value_that_does_not_fit_as_missing(mixed $value): void
    {
        self::assertNull((new DateTimeHandler())->hydrate(new DateTime(label: 'At'), $value, $this->hydrationContext()));
        self::assertFalse((new DateTimeHandler())->fits(new DateTime(label: 'At'), $value));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public function provideValuesThatDoNotFit(): iterable
    {
        yield 'another offset than UTC' => ['2026-11-13T10:30:00+01:00'];
        yield 'with milliseconds' => ['2026-11-13T09:30:00.000Z'];
        yield 'a day' => ['2026-11-13'];
        yield 'a time that does not exist' => ['2026-11-13T25:30:00Z'];
        yield 'with a trailing space' => ['2026-11-13T09:30:00Z '];
        yield 'a timestamp' => [1_794_562_200];
        yield 'nothing' => [null];
    }

    /**
     * @test
     */
    public function it_fits_a_time_in_utc_to_the_second(): void
    {
        self::assertTrue((new DateTimeHandler())->fits(new DateTime(label: 'At'), '2026-11-13T09:30:00Z'));
    }

    /**
     * @test
     *
     * @dataProvider provideValuesSavedInUtc
     */
    public function it_saves_a_time_with_another_offset_or_with_milliseconds_in_utc(string $value, string $saved): void
    {
        self::assertSame($saved, (new DateTimeHandler())->sanitize(new DateTime(label: 'At'), $value));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public function provideValuesSavedInUtc(): iterable
    {
        yield 'UTC already' => ['2026-11-13T09:30:00Z', '2026-11-13T09:30:00Z'];
        yield 'an offset' => ['2026-11-13T10:30:00+01:00', '2026-11-13T09:30:00Z'];
        yield 'an offset across midnight' => ['2026-11-13T23:30:00-02:00', '2026-11-14T01:30:00Z'];
        yield 'as JavaScript writes it' => ['2026-11-13T09:30:00.123Z', '2026-11-13T09:30:00Z'];
    }

    /**
     * @test
     */
    public function it_leaves_a_value_it_cannot_read_for_publish_to_refuse(): void
    {
        self::assertSame('next friday', (new DateTimeHandler())->sanitize(new DateTime(label: 'At'), 'next friday'));
        self::assertSame(42, (new DateTimeHandler())->sanitize(new DateTime(label: 'At'), 42));
        self::assertSame('2026-11-13', (new DateTimeHandler())->sanitize(new DateTime(label: 'At'), '2026-11-13'));
    }

    /**
     * @test
     */
    public function it_holds_no_references(): void
    {
        self::assertSame([], [...(new DateTimeHandler())->references(new DateTime(label: 'At'), '2026-11-13T09:30:00Z')]);
    }

    /**
     * @test
     */
    public function it_accepts_a_time_in_utc_and_a_missing_value(): void
    {
        $context = $this->prophesize(ExecutionContextInterface::class);
        $context->buildViolation(Argument::cetera())->shouldNotBeCalled();

        (new DateTimeHandler())->validate(new DateTime(label: 'At'), '2026-11-13T09:30:00Z', $context->reveal());
        (new DateTimeHandler())->validate(new DateTime(label: 'At'), null, $context->reveal());
    }

    /**
     * @test
     */
    public function it_rejects_a_value_that_is_not_a_time_in_utc(): void
    {
        $builder = $this->prophesize(ConstraintViolationBuilderInterface::class);
        $builder->addViolation()->shouldBeCalledOnce();

        $context = $this->prophesize(ExecutionContextInterface::class);
        $context->buildViolation('setono_sylius_cms.field.not_date_time')->willReturn($builder->reveal())->shouldBeCalledOnce();

        (new DateTimeHandler())->validate(new DateTime(label: 'At'), '2026-11-13', $context->reveal());
    }

    private function hydrationContext(): HydrationContext
    {
        return new HydrationContext(new RenderContext($this->prophesize(ChannelInterface::class)->reveal(), 'en_US', 'USD', null, '@guest', new \DateTimeImmutable()));
    }
}
