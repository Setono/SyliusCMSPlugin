<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Field;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Field\Entry;
use Setono\SyliusCMSPlugin\Field\EntryHandler;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Model\BlogCategory;
use Setono\SyliusCMSPlugin\Model\BlogCategoryTranslation;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Rendering\ReferenceMap;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;
use Setono\SyliusCMSPlugin\Tests\Unit\Field\Fixture\WithEntry;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

/**
 * The Entry field type: one entry, by its UUID, of one content type when it names one, which is a link. See "Shipped
 * field types" in docs/spec/extending.md
 */
final class EntryHandlerTest extends TestCase
{
    use ProphecyTrait;

    private const UUID = '0193a5be-7255-7f00-8a1b-2c3d4e5f6a7b';

    /**
     * @test
     */
    public function it_is_the_shipped_entry_field_type_shared_by_all_translations(): void
    {
        $fields = (new FieldReader())->read(WithEntry::class);

        self::assertSame('entry', $fields['category']->type->code);
        self::assertFalse($fields['category']->translatable);
        self::assertSame(EntryHandler::class, $fields['category']->handler());
        self::assertInstanceOf(Entry::class, $fields['category']->field);
        self::assertSame(BlogCategory::class, $fields['category']->field->contentType);
        self::assertNull((new Entry(label: 'Entry'))->contentType);
    }

    /**
     * @test
     */
    public function it_describes_a_uuid_with_the_code_of_its_content_type_in_its_schema(): void
    {
        self::assertSame(
            ['type' => 'string', 'format' => 'uuid', 'x-cms-options' => ['contentType' => 'blog_category']],
            self::handler()->schema(new Entry(label: 'Category', contentType: BlogCategory::class)),
        );
        self::assertSame(
            ['type' => 'string', 'format' => 'uuid', 'x-cms-options' => ['contentType' => null]],
            self::handler()->schema(new Entry(label: 'Entry')),
        );
    }

    /**
     * @test
     */
    public function it_hydrates_a_uuid_as_the_entry_the_reference_map_holds(): void
    {
        $category = new BlogCategory();
        $page = new Page();
        $references = Rendering::hydrationContext(references: new ReferenceMap(['entry' => [self::UUID => $category, $page->getUuid() => $page]]));

        self::assertSame($category, self::handler()->hydrate(new Entry(label: 'Category', contentType: BlogCategory::class), strtoupper(self::UUID), $references));
        self::assertSame($page, self::handler()->hydrate(new Entry(label: 'Entry'), $page->getUuid(), $references));
    }

    /**
     * The references loaded before rendering hold only the entries the shopper may see
     *
     * @test
     */
    public function it_hydrates_an_entry_that_is_gone_hidden_or_of_another_content_type_as_missing(): void
    {
        $page = new Page();
        $references = Rendering::hydrationContext(references: new ReferenceMap(['entry' => [$page->getUuid() => $page]]));
        $field = new Entry(label: 'Category', contentType: BlogCategory::class);

        self::assertNull(self::handler()->hydrate($field, self::UUID, $references));
        self::assertNull(self::handler()->hydrate($field, $page->getUuid(), $references));
        self::assertNull(self::handler()->hydrate($field, 'about-us', $references));
        self::assertNull(self::handler()->hydrate($field, [self::UUID], $references));
    }

    /**
     * @test
     */
    public function it_refers_to_its_entry_as_a_link_that_an_export_leaves_as_a_uuid(): void
    {
        self::assertEquals([new Reference('entry', self::UUID, false)], [...self::handler()->references(new Entry(label: 'Entry'), strtoupper(self::UUID))]);
        self::assertSame([], [...self::handler()->references(new Entry(label: 'Entry'), 'about-us')]);
        self::assertSame([], [...self::handler()->references(new Entry(label: 'Entry'), null)]);
    }

    /**
     * @test
     */
    public function it_fits_a_uuid_and_nothing_else(): void
    {
        self::assertTrue(self::handler()->fits(new Entry(label: 'Entry'), self::UUID));
        self::assertFalse(self::handler()->fits(new Entry(label: 'Entry'), 'about-us'));
        self::assertFalse(self::handler()->fits(new Entry(label: 'Entry'), [self::UUID]));
    }

    /**
     * @test
     */
    public function it_accepts_a_uuid_and_a_missing_value(): void
    {
        $context = $this->prophesize(ExecutionContextInterface::class);
        $context->buildViolation(Argument::cetera())->shouldNotBeCalled();

        self::handler()->validate(new Entry(label: 'Entry'), self::UUID, $context->reveal());
        self::handler()->validate(new Entry(label: 'Entry'), null, $context->reveal());
    }

    /**
     * @test
     */
    public function it_rejects_a_value_that_is_not_a_uuid(): void
    {
        $builder = $this->prophesize(ConstraintViolationBuilderInterface::class);
        $builder->addViolation()->shouldBeCalledOnce();

        $context = $this->prophesize(ExecutionContextInterface::class);
        $context->buildViolation('setono_sylius_cms.field.not_entry')->willReturn($builder->reveal())->shouldBeCalledOnce();

        self::handler()->validate(new Entry(label: 'Entry'), 'about-us', $context->reveal());
    }

    private static function handler(): EntryHandler
    {
        return new EntryHandler(new ContentTypeRegistry([
            'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
            'blog_category' => ['model' => BlogCategory::class, 'translation' => BlogCategoryTranslation::class, 'resource' => 'setono_sylius_cms.blog_category'],
        ]));
    }
}
