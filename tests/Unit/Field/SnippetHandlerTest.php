<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Field;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Field\HydrationContext;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Field\Snippet;
use Setono\SyliusCMSPlugin\Field\SnippetHandler;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\Snippet as SnippetModel;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Rendering\ReferenceMap;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;
use Setono\SyliusCMSPlugin\Tests\Unit\Field\Fixture\WithSnippet;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

final class SnippetHandlerTest extends TestCase
{
    use ProphecyTrait;

    private const UUID = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e';

    /**
     * @test
     */
    public function it_is_the_shipped_snippet_field_type_shared_by_all_translations(): void
    {
        $fields = (new FieldReader())->read(WithSnippet::class);

        self::assertSame('snippet', $fields['footer']->type->code);
        self::assertFalse($fields['footer']->translatable);
        self::assertSame(SnippetHandler::class, $fields['footer']->handler());
        self::assertInstanceOf(Snippet::class, $fields['footer']->field);
    }

    /**
     * @test
     */
    public function it_describes_a_snippet_uuid_in_its_schema(): void
    {
        self::assertSame(['type' => 'string', 'format' => 'uuid'], (new SnippetHandler())->schema(new Snippet(label: 'Footer')));
    }

    /**
     * @test
     */
    public function it_hydrates_a_uuid_as_the_snippet_the_reference_map_holds(): void
    {
        $snippet = new SnippetModel();

        self::assertSame($snippet, (new SnippetHandler())->hydrate(new Snippet(label: 'Footer'), self::UUID, self::context($snippet)));
        self::assertSame($snippet, (new SnippetHandler())->hydrate(new Snippet(label: 'Footer'), strtoupper(self::UUID), self::context($snippet)));
    }

    /**
     * @test
     */
    public function it_hydrates_a_reference_that_is_broken_not_visible_or_not_a_snippet_as_missing(): void
    {
        $handler = new SnippetHandler();

        self::assertNull($handler->hydrate(new Snippet(label: 'Footer'), self::UUID, self::context(null)));
        self::assertNull($handler->hydrate(new Snippet(label: 'Footer'), self::UUID, self::context(new Page())));
        self::assertNull($handler->hydrate(new Snippet(label: 'Footer'), 'footer', self::context(new SnippetModel())));
        self::assertNull($handler->hydrate(new Snippet(label: 'Footer'), [self::UUID], self::context(new SnippetModel())));
    }

    /**
     * @test
     */
    public function it_fits_a_uuid_and_nothing_else(): void
    {
        $handler = new SnippetHandler();

        self::assertTrue($handler->fits(new Snippet(label: 'Footer'), self::UUID));
        self::assertFalse($handler->fits(new Snippet(label: 'Footer'), 'footer'));
        self::assertFalse($handler->fits(new Snippet(label: 'Footer'), 7));
        self::assertFalse($handler->fits(new Snippet(label: 'Footer'), [self::UUID]));
    }

    /**
     * @test
     */
    public function it_refers_to_its_snippet_as_an_entry_that_is_a_dependency(): void
    {
        self::assertEquals(
            [new Reference('entry', self::UUID, true)],
            [...(new SnippetHandler())->references(new Snippet(label: 'Footer'), strtoupper(self::UUID))],
        );
        self::assertSame([], [...(new SnippetHandler())->references(new Snippet(label: 'Footer'), 'footer')]);
        self::assertSame([], [...(new SnippetHandler())->references(new Snippet(label: 'Footer'), null)]);
    }

    /**
     * @test
     */
    public function it_accepts_a_uuid_and_a_missing_value(): void
    {
        $context = $this->prophesize(ExecutionContextInterface::class);
        $context->buildViolation(Argument::cetera())->shouldNotBeCalled();

        $handler = new SnippetHandler();
        $handler->validate(new Snippet(label: 'Footer'), self::UUID, $context->reveal());
        $handler->validate(new Snippet(label: 'Footer'), null, $context->reveal());
    }

    /**
     * @test
     */
    public function it_rejects_a_value_that_is_not_a_uuid(): void
    {
        $builder = $this->prophesize(ConstraintViolationBuilderInterface::class);
        $builder->addViolation()->shouldBeCalledOnce();

        $context = $this->prophesize(ExecutionContextInterface::class);
        $context->buildViolation('setono_sylius_cms.field.not_snippet')->willReturn($builder->reveal())->shouldBeCalledOnce();

        (new SnippetHandler())->validate(new Snippet(label: 'Footer'), 'footer', $context->reveal());
    }

    private static function context(?object $entry): HydrationContext
    {
        return Rendering::hydrationContext(references: new ReferenceMap(null === $entry ? [] : ['entry' => [self::UUID => $entry]]));
    }
}
