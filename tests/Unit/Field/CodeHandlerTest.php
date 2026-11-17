<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Field;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Field\Code;
use Setono\SyliusCMSPlugin\Field\CodeHandler;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;
use Setono\SyliusCMSPlugin\Tests\Unit\Field\Fixture\WithCode;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

final class CodeHandlerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_is_the_shipped_code_field_type_shared_by_all_translations_and_never_bound(): void
    {
        $fields = (new FieldReader())->read(WithCode::class);

        self::assertSame('code', $fields['embed']->type->code);
        self::assertSame([], $fields['embed']->type->bindable);
        self::assertNull($fields['embed']->type->control);
        self::assertFalse($fields['embed']->translatable);
        self::assertNull($fields['embed']->default);
        self::assertSame(CodeHandler::class, $fields['embed']->handler());
    }

    /**
     * @test
     */
    public function it_describes_a_string_in_its_schema(): void
    {
        self::assertSame(['type' => 'string'], (new CodeHandler())->schema(new Code(label: 'Code')));
    }

    /**
     * @test
     */
    public function it_hydrates_a_string_as_it_is_markup_and_scripts_included(): void
    {
        $code = "<div id=\"reviews\"></div>\n<script src=\"https://widgets.example.com/reviews.js\" async></script>";

        self::assertSame($code, (new CodeHandler())->hydrate(new Code(label: 'Code'), $code, Rendering::hydrationContext()));
    }

    /**
     * @test
     */
    public function it_hydrates_a_value_that_does_not_fit_as_missing(): void
    {
        $handler = new CodeHandler();

        self::assertNull($handler->hydrate(new Code(label: 'Code'), 42, Rendering::hydrationContext()));
        self::assertNull($handler->hydrate(new Code(label: 'Code'), ['<p>Hi</p>'], Rendering::hydrationContext()));
    }

    /**
     * @test
     */
    public function it_fits_a_string_and_nothing_else(): void
    {
        $handler = new CodeHandler();

        self::assertTrue($handler->fits(new Code(label: 'Code'), '<p>Hi</p>'));
        self::assertFalse($handler->fits(new Code(label: 'Code'), 42));
        self::assertFalse($handler->fits(new Code(label: 'Code'), ['en_US' => '<p>Hi</p>']));
    }

    /**
     * @test
     */
    public function it_holds_no_references(): void
    {
        self::assertSame([], [...(new CodeHandler())->references(new Code(label: 'Code'), '<a href="/en_US/about">About</a>')]);
    }

    /**
     * @test
     */
    public function it_accepts_a_string_and_a_missing_value(): void
    {
        $context = $this->prophesize(ExecutionContextInterface::class);
        $context->buildViolation(Argument::cetera())->shouldNotBeCalled();

        $handler = new CodeHandler();
        $handler->validate(new Code(label: 'Code'), '<script>alert(1)</script>', $context->reveal());
        $handler->validate(new Code(label: 'Code'), null, $context->reveal());
    }

    /**
     * @test
     */
    public function it_rejects_a_value_that_is_not_a_string(): void
    {
        $builder = $this->prophesize(ConstraintViolationBuilderInterface::class);
        $builder->addViolation()->shouldBeCalledOnce();

        $context = $this->prophesize(ExecutionContextInterface::class);
        $context->buildViolation('setono_sylius_cms.field.not_code')->willReturn($builder->reveal())->shouldBeCalledOnce();

        (new CodeHandler())->validate(new Code(label: 'Code'), ['<p>Hi</p>'], $context->reveal());
    }
}
