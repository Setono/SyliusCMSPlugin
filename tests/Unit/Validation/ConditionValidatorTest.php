<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Validation;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Condition\Kind\ProductInTaxonKind;
use Setono\SyliusCMSPlugin\Field\ChoiceHandler;
use Setono\SyliusCMSPlugin\Field\DateTimeHandler;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\ListFieldHandler;
use Setono\SyliusCMSPlugin\Field\ToggleHandler;
use Setono\SyliusCMSPlugin\Localization\FallbackChain;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Tests\Support\Bindings;
use Setono\SyliusCMSPlugin\Tests\Support\Conditions;
use Setono\SyliusCMSPlugin\Tests\Support\Sources;
use Setono\SyliusCMSPlugin\Validation\ConditionValidator;
use Setono\SyliusCMSPlugin\Validation\FieldConstraints;
use Setono\SyliusCMSPlugin\Validation\FieldValidator;
use Setono\SyliusCMSPlugin\Validation\ValidationError;
use Sylius\Resource\Translation\Provider\ImmutableTranslationLocaleProvider;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Validator\Validation;

/**
 * A visibility condition is refused on publish when it isn't one, its kind is one the code no longer has, or its
 * parameter values don't fit its kind, such as a date window that ends before it starts. See "Validation" in
 * docs/spec/publishing.md
 */
final class ConditionValidatorTest extends TestCase
{
    /**
     * @test
     */
    public function it_accepts_a_condition_whose_parameters_fit_its_kind(): void
    {
        self::assertSame([], $this->validator()->validate(['kind' => 'locale_is', 'negate' => true, 'params' => ['locales' => ['da_DK']]]));
        self::assertSame([], $this->validator()->validate(['kind' => 'date_window', 'negate' => false, 'params' => ['from' => '2026-12-01T00:00:00Z', 'until' => '2026-12-24T00:00:00Z']]));
        self::assertSame([], $this->validator()->validate(['kind' => 'date_window', 'negate' => false, 'params' => ['until' => '2026-12-24T00:00:00Z']]));
        self::assertSame([], $this->validator()->validate(['kind' => 'signed_in', 'negate' => false, 'params' => []]));
    }

    /**
     * @test
     */
    public function it_refuses_a_parameter_value_its_field_refuses_at_that_parameter(): void
    {
        self::assertEquals(
            [new ValidationError(null, 'locales', null, 'The value you selected is not a valid choice.')],
            $this->validator()->validate(['kind' => 'locale_is', 'negate' => false, 'params' => ['locales' => ['sv_SE']]]),
        );
        self::assertEquals(
            [new ValidationError(null, 'from', null, 'This value should be a date and time.')],
            $this->validator()->validate(['kind' => 'date_window', 'negate' => false, 'params' => ['from' => 'next Monday']]),
        );
        self::assertEquals(
            [new ValidationError(null, 'taxons', null, 'This field is required.')],
            $this->validator()->validate(['kind' => 'product_in_taxon', 'negate' => false, 'params' => ['taxons' => [], 'includeChildren' => true]]),
        );
    }

    /**
     * "Until" is exclusive, so a window that ends where it starts never shows either
     *
     * @test
     */
    public function it_refuses_a_date_window_that_ends_before_it_starts_or_where_it_starts_at_its_end(): void
    {
        foreach (['2026-11-30T00:00:00Z', '2026-12-01T00:00:00Z'] as $until) {
            self::assertEquals(
                [new ValidationError(null, 'until', null, 'Until must come after From.')],
                $this->validator()->validate(['kind' => 'date_window', 'negate' => true, 'params' => ['from' => '2026-12-01T00:00:00Z', 'until' => $until]]),
            );
        }
    }

    /**
     * @test
     */
    public function it_refuses_a_condition_of_a_kind_the_code_no_longer_has_or_that_is_not_a_condition_as_a_whole(): void
    {
        self::assertEquals(
            [new ValidationError(null, null, null, 'The condition kind "brand" no longer exists, so the block isn\'t shown. Remove the condition.')],
            $this->validator()->validate(['kind' => 'brand', 'negate' => false, 'params' => ['brands' => ['ACME']]]),
        );
        self::assertEquals(
            [new ValidationError(null, null, null, 'This isn\'t a condition. Remove it.')],
            $this->validator()->validate(['kind' => 'locale_is', 'negate' => 'no', 'params' => []]),
        );
    }

    private function validator(): ConditionValidator
    {
        $translator = BlockTreeValidatorTest::translator();
        $handlers = new FieldHandlerLocator(new ServiceLocator([
            ChoiceHandler::class => static fn (): ChoiceHandler => new ChoiceHandler($translator),
            DateTimeHandler::class => static fn (): DateTimeHandler => new DateTimeHandler(),
            ListFieldHandler::class => static fn (): ListFieldHandler => Sources::listFieldHandler(),
            ToggleHandler::class => static fn (): ToggleHandler => new ToggleHandler(),
        ]));
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->setTranslator($translator)->setTranslationDomain('validators')->getValidator();
        $registry = Conditions::registry(['product_in_taxon' => new ProductInTaxonKind()]);

        return new ConditionValidator(
            $registry,
            new FieldValidator(
                new FieldConstraints($handlers, $validator),
                new FallbackValues($handlers, new FallbackChain(new ImmutableTranslationLocaleProvider(['en_US'], 'en_US'))),
                $validator,
                $translator,
                Bindings::validator(),
            ),
            $translator,
        );
    }
}
