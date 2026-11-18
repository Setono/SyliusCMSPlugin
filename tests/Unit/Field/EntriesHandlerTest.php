<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Field;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Field\Entries;
use Setono\SyliusCMSPlugin\Field\EntriesHandler;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Model\BlogCategory;
use Setono\SyliusCMSPlugin\Model\BlogCategoryInterface;
use Setono\SyliusCMSPlugin\Model\BlogCategoryTranslation;
use Setono\SyliusCMSPlugin\Model\BlogPost;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Rendering\ReferenceMap;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;
use Setono\SyliusCMSPlugin\Tests\Support\Sources;
use Setono\SyliusCMSPlugin\Value\ItemList;

/**
 * The Entries field type: entries hand-picked by UUID, of one content type when it names one, which are links, and the
 * UUIDs of a content type's own field declared indexed go to the reference table on publish. See "Shipped field types"
 * in docs/spec/extending.md
 */
final class EntriesHandlerTest extends TestCase
{
    /**
     * @test
     */
    public function it_is_the_shipped_entries_field_type_shared_by_all_translations(): void
    {
        $fields = (new FieldReader())->readContentType(BlogPost::class, false);

        self::assertSame('entries', $fields['categories']->type->code);
        self::assertFalse($fields['categories']->translatable);
        self::assertSame(EntriesHandler::class, $fields['categories']->handler());

        $field = $fields['categories']->field;
        self::assertInstanceOf(Entries::class, $field);
        self::assertSame(BlogCategory::class, $field->contentType);
        self::assertTrue($field->indexed);
        self::assertFalse($field->sources);
    }

    /**
     * @test
     */
    public function it_lists_entries_of_any_content_type_and_is_not_indexed_unless_it_says_otherwise(): void
    {
        $field = new Entries(label: 'Entries');

        self::assertNull($field->contentType);
        self::assertFalse($field->indexed);
        self::assertSame('entry', $field->itemType());
        self::assertTrue($field->sources);
        self::assertSame(8, $field->limit);
    }

    /**
     * The builder's picker searches the content type the field names, by its code
     *
     * @test
     */
    public function it_names_the_code_of_its_content_type_among_its_options_in_its_schema(): void
    {
        self::assertSame(
            [
                'type' => 'array',
                'items' => ['type' => 'string', 'minLength' => 1],
                'maxItems' => 3,
                'x-cms-options' => ['max' => 3, 'sources' => [], 'limit' => 8, 'contentType' => 'blog_category'],
            ],
            self::handler()->schema(new Entries(label: 'Categories', contentType: BlogCategoryInterface::class, max: 3, sources: false)),
        );
        self::assertSame(
            ['max' => null, 'sources' => [], 'limit' => 8, 'contentType' => null],
            self::handler()->schema(new Entries(label: 'Entries'))['x-cms-options'] ?? null,
        );
    }

    /**
     * The references loaded before rendering hold only the entries the shopper may see
     *
     * @test
     */
    public function it_keeps_the_editors_order_of_the_entries_the_shopper_may_see_of_its_content_type(): void
    {
        $news = new BlogCategory();
        $recipes = new BlogCategory();
        $page = new Page();

        $list = self::handler()->hydrate(
            new Entries(label: 'Categories', contentType: BlogCategory::class),
            [$recipes->getUuid(), $page->getUuid(), 'a-deleted-category', $news->getUuid()],
            Rendering::hydrationContext(references: new ReferenceMap(['entry' => [
                $news->getUuid() => $news,
                $recipes->getUuid() => $recipes,
                $page->getUuid() => $page,
            ]])),
        );

        self::assertInstanceOf(ItemList::class, $list);
        self::assertSame([$recipes, $news], [...$list]);
    }

    /**
     * @test
     */
    public function it_refers_to_its_entries_as_links_that_an_export_leaves_as_uuids(): void
    {
        $uuid = '0193a5be-7255-7f00-8a1b-2c3d4e5f6a7b';

        self::assertEquals(
            [new Reference('entry', $uuid, false)],
            [...self::handler()->references(new Entries(label: 'Categories', contentType: BlogCategory::class), [$uuid, $uuid])],
        );
    }

    /**
     * @test
     */
    public function it_fits_a_list_of_uuids(): void
    {
        $field = new Entries(label: 'Categories', contentType: BlogCategory::class);

        self::assertTrue(self::handler()->fits($field, ['0193a5be-7255-7f00-8a1b-2c3d4e5f6a7b']));
        self::assertFalse(self::handler()->fits($field, '0193a5be-7255-7f00-8a1b-2c3d4e5f6a7b'));
    }

    private static function handler(): EntriesHandler
    {
        return new EntriesHandler(Sources::listFieldHandler(), new ContentTypeRegistry([
            'page' => ['model' => Page::class, 'translation' => null, 'resource' => 'setono_sylius_cms.page'],
            'blog_category' => ['model' => BlogCategory::class, 'translation' => BlogCategoryTranslation::class, 'resource' => 'setono_sylius_cms.blog_category'],
        ]));
    }
}
