<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Field;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Field\Choice;
use Setono\SyliusCMSPlugin\Field\HydrationContext;
use Setono\SyliusCMSPlugin\Field\Text;
use Setono\SyliusCMSPlugin\Field\TextHandler;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

final class TextHandlerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function its_schema_is_a_string_with_its_own_options(): void
    {
        self::assertSame(
            ['type' => 'string', 'x-cms-options' => ['maxLength' => null]],
            (new TextHandler())->schema(new Text(label: 'Heading')),
        );
    }

    /**
     * @test
     */
    public function its_schema_limits_the_length_when_the_field_has_a_max_length(): void
    {
        self::assertSame(
            ['type' => 'string', 'maxLength' => 60, 'x-cms-options' => ['maxLength' => 60]],
            (new TextHandler())->schema(new Text(label: 'Heading', maxLength: 60)),
        );
    }

    /**
     * @test
     */
    public function it_hydrates_a_string_as_it_is(): void
    {
        self::assertSame('Hello', (new TextHandler())->hydrate(new Text(label: 'Heading'), 'Hello', $this->hydrationContext()));
    }

    /**
     * @test
     */
    public function it_hydrates_a_value_that_does_not_fit_as_missing(): void
    {
        $handler = new TextHandler();

        self::assertNull($handler->hydrate(new Text(label: 'Heading'), 42, $this->hydrationContext()));
        self::assertNull($handler->hydrate(new Text(label: 'Heading'), ['en_US' => 'Hello'], $this->hydrationContext()));
        self::assertNull($handler->hydrate(new Text(label: 'Heading'), null, $this->hydrationContext()));
    }

    /**
     * @test
     */
    public function it_holds_no_references(): void
    {
        self::assertSame([], [...(new TextHandler())->references(new Text(label: 'Heading'), 'Hello')]);
    }

    /**
     * @test
     */
    public function it_accepts_a_string_and_a_missing_value(): void
    {
        $context = $this->prophesize(ExecutionContextInterface::class);
        $context->buildViolation(Argument::cetera())->shouldNotBeCalled();

        $handler = new TextHandler();
        $handler->validate(new Text(label: 'Heading', maxLength: 5), 'Hello', $context->reveal());
        $handler->validate(new Text(label: 'Heading', maxLength: 5), 'Rødgr', $context->reveal());
        $handler->validate(new Text(label: 'Heading'), null, $context->reveal());
    }

    /**
     * @test
     */
    public function it_rejects_a_value_that_is_not_a_string(): void
    {
        $builder = $this->prophesize(ConstraintViolationBuilderInterface::class);
        $builder->addViolation()->shouldBeCalledOnce();

        $context = $this->prophesize(ExecutionContextInterface::class);
        $context->buildViolation('setono_sylius_cms.field.not_text')->willReturn($builder->reveal())->shouldBeCalledOnce();

        (new TextHandler())->validate(new Text(label: 'Heading'), 42, $context->reveal());
    }

    /**
     * @test
     */
    public function it_rejects_a_string_longer_than_the_max_length(): void
    {
        $builder = $this->prophesize(ConstraintViolationBuilderInterface::class);
        $builder->setParameter('{{ limit }}', '4')->willReturn($builder->reveal())->shouldBeCalledOnce();
        $builder->addViolation()->shouldBeCalledOnce();

        $context = $this->prophesize(ExecutionContextInterface::class);
        $context->buildViolation('setono_sylius_cms.field.too_long')->willReturn($builder->reveal())->shouldBeCalledOnce();

        (new TextHandler())->validate(new Text(label: 'Heading', maxLength: 4), 'Hello', $context->reveal());
    }

    /**
     * @test
     */
    public function it_refuses_a_field_of_another_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new TextHandler())->schema(new Choice(label: 'Level'));
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
