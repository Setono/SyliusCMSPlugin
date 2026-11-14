<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Field;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Field\HydrationContext;
use Setono\SyliusCMSPlugin\Field\Textarea;
use Setono\SyliusCMSPlugin\Field\TextHandler;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Tests\Unit\Field\Fixture\WithTextarea;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

/**
 * Textarea stores a string with its newlines, like Text stores a single line, so the Text handler handles both
 */
final class TextareaTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_is_the_shipped_textarea_field_type_translatable_by_default(): void
    {
        $fields = (new FieldReader())->read(WithTextarea::class);

        self::assertSame('textarea', $fields['excerpt']->type->code);
        self::assertTrue($fields['excerpt']->translatable);
        self::assertSame(TextHandler::class, $fields['excerpt']->handler());
        self::assertInstanceOf(Textarea::class, $fields['excerpt']->field);
        self::assertSame(160, $fields['excerpt']->field->maxLength);
    }

    /**
     * @test
     */
    public function its_schema_is_a_string_with_its_max_length(): void
    {
        self::assertSame(
            ['type' => 'string', 'maxLength' => 160, 'x-cms-options' => ['maxLength' => 160]],
            (new TextHandler())->schema(new Textarea(label: 'Excerpt', maxLength: 160)),
        );
        self::assertSame(
            ['type' => 'string', 'x-cms-options' => ['maxLength' => null]],
            (new TextHandler())->schema(new Textarea(label: 'Excerpt')),
        );
    }

    /**
     * @test
     */
    public function it_hydrates_a_string_with_its_newlines_kept(): void
    {
        self::assertSame("One\nTwo\n", (new TextHandler())->hydrate(new Textarea(label: 'Excerpt'), "One\nTwo\n", $this->hydrationContext()));
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

        (new TextHandler())->validate(new Textarea(label: 'Excerpt', maxLength: 4), "On\nTwo", $context->reveal());
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
