<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Validation\Constraint;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Tests\Unit\Validation\BlockTreeValidatorTest;
use Setono\SyliusCMSPlugin\Validation\Constraint\EndsAfterStart;
use Setono\SyliusCMSPlugin\Validation\Constraint\EndsAfterStartValidator;
use Setono\SyliusCMSPlugin\Validation\FieldValues;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class EndsAfterStartTest extends TestCase
{
    /**
     * @test
     */
    public function it_refuses_an_end_before_its_start_or_at_it_at_the_end_naming_both_by_their_labels(): void
    {
        foreach (['2026-11-30T23:00:00Z', '2026-12-01T00:00:00Z'] as $until) {
            self::assertSame(
                [['until', 'Until must come after From.']],
                self::violations(new FieldValues(['from' => '2026-12-01T00:00:00Z', 'until' => $until], ['from' => 'From', 'until' => 'Until'])),
            );
        }
    }

    /**
     * @test
     */
    public function it_accepts_an_end_after_its_start_a_missing_end_and_values_that_are_no_dates(): void
    {
        self::assertSame([], self::violations(new FieldValues(['from' => '2026-12-01T00:00:00Z', 'until' => '2026-12-01T00:00:01Z'])));
        self::assertSame([], self::violations(new FieldValues(['from' => '2026-12-01', 'until' => '2026-12-02'])));
        self::assertSame([], self::violations(new FieldValues(['from' => '2026-12-01T00:00:00Z'])));
        self::assertSame([], self::violations(new FieldValues(['until' => '2026-12-01T00:00:00Z'])));
        self::assertSame([], self::violations(new FieldValues(['from' => 'Monday', 'until' => ['2026-01-01']])));
        self::assertSame([], self::violations(new FieldValues(['from' => 'not a date', 'until' => '2026-01-01'])));
    }

    /**
     * @test
     */
    public function it_takes_a_message_of_its_own_and_targets_the_class(): void
    {
        $constraint = new EndsAfterStart(start: 'opens', end: 'closes', message: 'app.closes_before_opening');

        self::assertSame(['opens', 'closes', 'app.closes_before_opening'], [$constraint->start, $constraint->end, $constraint->message]);
        self::assertSame(EndsAfterStart::CLASS_CONSTRAINT, $constraint->getTargets());
    }

    /**
     * @test
     */
    public function it_checks_only_the_values_of_fields(): void
    {
        $violations = Validation::createValidator()->validate(['from' => '2026-12-01T00:00:00Z'], new EndsAfterStart(start: 'from', end: 'until'));

        self::assertCount(1, $violations);
        self::assertSame(sprintf('This value should be of type %s.', FieldValues::class), $violations[0]?->getMessage());
    }

    /**
     * @test
     */
    public function it_validates_only_its_own_constraint(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        (new EndsAfterStartValidator())->validate(new FieldValues([]), new NotBlank());
    }

    /**
     * @return list<array{string, string}>
     */
    private static function violations(FieldValues $values): array
    {
        return array_map(
            static fn (ConstraintViolationInterface $violation): array => [$violation->getPropertyPath(), (string) $violation->getMessage()],
            iterator_to_array(self::validator()->validate($values, new EndsAfterStart(start: 'from', end: 'until')), false),
        );
    }

    private static function validator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()->setTranslator(BlockTreeValidatorTest::translator())->setTranslationDomain('validators')->getValidator();
    }
}
