<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Field;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Field\HydrationContext;
use Setono\SyliusCMSPlugin\Field\RichText;
use Setono\SyliusCMSPlugin\Field\RichTextHandler;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\RichText\RichTextSchema;
use Setono\SyliusCMSPlugin\Tests\Unit\Field\Fixture\WithRichText;
use Setono\SyliusCMSPlugin\Value\RichText as RichTextValue;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

final class RichTextHandlerTest extends TestCase
{
    use ProphecyTrait;

    private const DOCUMENT = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [
        ['type' => 'text', 'text' => 'A '],
        ['type' => 'text', 'text' => 'bold', 'marks' => [['type' => 'bold']]],
        ['type' => 'text', 'text' => ' word'],
    ]]]];

    /**
     * @test
     */
    public function it_is_the_shipped_rich_text_field_type_translatable_by_default(): void
    {
        $fields = (new FieldReader())->read(WithRichText::class);

        self::assertSame('rich_text', $fields['content']->type->code);
        self::assertTrue($fields['content']->translatable);
        self::assertSame(RichTextHandler::class, $fields['content']->handler());
    }

    /**
     * @test
     */
    public function it_describes_a_prosemirror_document_in_its_schema(): void
    {
        self::assertSame(
            [
                'type' => 'object',
                'properties' => ['type' => ['const' => 'doc'], 'content' => ['type' => 'array']],
                'required' => ['type', 'content'],
            ],
            self::handler()->schema(new RichText(label: 'Content')),
        );
    }

    /**
     * @test
     */
    public function it_hydrates_a_document_as_a_rich_text_value_with_its_html(): void
    {
        $value = self::handler()->hydrate(new RichText(label: 'Content'), self::DOCUMENT, $this->hydrationContext());

        self::assertInstanceOf(RichTextValue::class, $value);
        self::assertSame(self::DOCUMENT, $value->document);
        self::assertSame('<p>A <strong>bold</strong> word</p>', $value->html);
    }

    /**
     * @test
     */
    public function it_hydrates_what_the_schema_has_and_drops_the_rest(): void
    {
        $value = self::handler()->hydrate(new RichText(label: 'Content'), ['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hi', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']]]]]],
            ['type' => 'heading', 'attrs' => ['level' => 1], 'content' => [['type' => 'text', 'text' => 'Title']]],
        ]], $this->hydrationContext());

        self::assertInstanceOf(RichTextValue::class, $value);
        self::assertSame('<p>Hi</p><p>Title</p>', $value->html);
    }

    /**
     * @test
     */
    public function it_hydrates_an_empty_document_and_a_value_that_does_not_fit_as_missing(): void
    {
        $handler = self::handler();
        $field = new RichText(label: 'Content');

        self::assertNull($handler->hydrate($field, ['type' => 'doc', 'content' => [['type' => 'paragraph']]], $this->hydrationContext()));
        self::assertNull($handler->hydrate($field, '<p>Hello</p>', $this->hydrationContext()));
        self::assertNull($handler->hydrate($field, null, $this->hydrationContext()));
    }

    /**
     * @test
     */
    public function it_fits_a_document_even_an_empty_one_and_nothing_else(): void
    {
        $handler = self::handler();
        $field = new RichText(label: 'Content');

        self::assertTrue($handler->fits($field, self::DOCUMENT));
        self::assertTrue($handler->fits($field, ['type' => 'doc', 'content' => [['type' => 'paragraph']]]));
        self::assertFalse($handler->fits($field, '<p>Hello</p>'));
        self::assertFalse($handler->fits($field, ['type' => 'paragraph']));
    }

    /**
     * @test
     */
    public function it_holds_no_references(): void
    {
        self::assertSame([], [...self::handler()->references(new RichText(label: 'Content'), self::DOCUMENT)]);
    }

    /**
     * @test
     */
    public function it_sanitises_a_document_before_it_is_saved(): void
    {
        self::assertSame(
            ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hi']]]]],
            self::handler()->sanitize(new RichText(label: 'Content'), ['type' => 'doc', 'content' => [
                ['type' => 'paragraph', 'attrs' => ['style' => 'color: red'], 'content' => [['type' => 'text', 'text' => 'Hi']]],
                ['type' => 'iframe', 'attrs' => ['src' => 'https://example.com']],
            ]]),
        );
    }

    /**
     * @test
     */
    public function it_saves_a_value_that_is_not_a_document_as_it_is_because_invalid_content_saves(): void
    {
        self::assertSame('<p>Hello</p>', self::handler()->sanitize(new RichText(label: 'Content'), '<p>Hello</p>'));
    }

    /**
     * @test
     */
    public function it_accepts_a_document_and_a_missing_value(): void
    {
        $context = $this->prophesize(ExecutionContextInterface::class);
        $context->buildViolation(Argument::cetera())->shouldNotBeCalled();

        self::handler()->validate(new RichText(label: 'Content'), self::DOCUMENT, $context->reveal());
        self::handler()->validate(new RichText(label: 'Content'), null, $context->reveal());
    }

    /**
     * @test
     */
    public function it_rejects_a_value_that_is_not_a_document(): void
    {
        $builder = $this->prophesize(ConstraintViolationBuilderInterface::class);
        $builder->addViolation()->shouldBeCalledOnce();

        $context = $this->prophesize(ExecutionContextInterface::class);
        $context->buildViolation('setono_sylius_cms.field.not_rich_text')->willReturn($builder->reveal())->shouldBeCalledOnce();

        self::handler()->validate(new RichText(label: 'Content'), '<p>Hello</p>', $context->reveal());
    }

    private static function handler(): RichTextHandler
    {
        return new RichTextHandler(new RichTextSchema());
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
