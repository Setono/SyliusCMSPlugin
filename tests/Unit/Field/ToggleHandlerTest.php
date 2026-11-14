<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Field;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Field\HydrationContext;
use Setono\SyliusCMSPlugin\Field\Toggle;
use Setono\SyliusCMSPlugin\Field\ToggleHandler;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Tests\Unit\Field\Fixture\WithToggle;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

final class ToggleHandlerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_is_the_shipped_toggle_field_type_shared_by_all_translations_by_default(): void
    {
        $fields = (new FieldReader())->read(WithToggle::class);

        self::assertSame('toggle', $fields['on']->type->code);
        self::assertFalse($fields['on']->translatable);
        self::assertFalse($fields['on']->default);
        self::assertSame(ToggleHandler::class, $fields['on']->handler());
    }

    /**
     * @test
     */
    public function its_schema_is_a_boolean(): void
    {
        self::assertSame(['type' => 'boolean'], (new ToggleHandler())->schema(new Toggle(label: 'Full width')));
    }

    /**
     * @test
     */
    public function it_hydrates_a_boolean_as_it_is(): void
    {
        $handler = new ToggleHandler();

        self::assertTrue($handler->hydrate(new Toggle(label: 'Full width'), true, $this->hydrationContext()));
        self::assertFalse($handler->hydrate(new Toggle(label: 'Full width'), false, $this->hydrationContext()));
    }

    /**
     * @test
     */
    public function it_hydrates_a_value_that_does_not_fit_as_missing(): void
    {
        $handler = new ToggleHandler();

        self::assertNull($handler->hydrate(new Toggle(label: 'Full width'), 1, $this->hydrationContext()));
        self::assertNull($handler->hydrate(new Toggle(label: 'Full width'), 'true', $this->hydrationContext()));
    }

    /**
     * @test
     */
    public function it_holds_no_references(): void
    {
        self::assertSame([], [...(new ToggleHandler())->references(new Toggle(label: 'Full width'), true)]);
    }

    /**
     * @test
     */
    public function it_accepts_a_boolean_and_a_missing_value(): void
    {
        $context = $this->prophesize(ExecutionContextInterface::class);
        $context->buildViolation(Argument::cetera())->shouldNotBeCalled();

        $handler = new ToggleHandler();
        $handler->validate(new Toggle(label: 'Full width'), true, $context->reveal());
        $handler->validate(new Toggle(label: 'Full width'), false, $context->reveal());
        $handler->validate(new Toggle(label: 'Full width'), null, $context->reveal());
    }

    /**
     * @test
     */
    public function it_rejects_a_value_that_is_not_a_boolean(): void
    {
        $builder = $this->prophesize(ConstraintViolationBuilderInterface::class);
        $builder->addViolation()->shouldBeCalledOnce();

        $context = $this->prophesize(ExecutionContextInterface::class);
        $context->buildViolation('setono_sylius_cms.field.not_toggle')->willReturn($builder->reveal())->shouldBeCalledOnce();

        (new ToggleHandler())->validate(new Toggle(label: 'Full width'), 'yes', $context->reveal());
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
