<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Collection;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Collection\IndexedFields;
use Setono\SyliusCMSPlugin\Model\BlogCategory;
use Setono\SyliusCMSPlugin\Model\BlogCategoryTranslation;
use Setono\SyliusCMSPlugin\Model\BlogPost;
use Setono\SyliusCMSPlugin\Model\BlogPostTranslation;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Registry\ContentTypeFields;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Tests\Unit\Collection\Fixture\Course;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Workshop;

/**
 * Which of a content type's own fields are indexed, what an entry's indexed fields hold, and which of them names its
 * categories. See "Categories" in docs/spec/collections.md
 */
final class IndexedFieldsTest extends TestCase
{
    private const NEWS = '0193a5be-7255-7f00-8a1b-2c3d4e5f6a7b';

    private const RECIPES = '0193a5be-7255-7f00-8a1b-2c3d4e5f6a7c';

    /**
     * @test
     */
    public function it_finds_the_indexed_entries_fields_of_a_content_type(): void
    {
        $fields = self::indexedFields();

        self::assertSame(['categories'], array_keys($fields->of(self::registry()->get('blog_post'))));
        self::assertSame([], $fields->of(self::registry()->get('blog_category')));
        self::assertSame([], $fields->of(self::registry()->get('page')));
    }

    /**
     * @test
     */
    public function it_reads_the_uuids_each_indexed_field_of_an_entry_holds_lowercase_once_each(): void
    {
        $post = new BlogPost();
        $post->setCategories([strtoupper(self::RECIPES), self::NEWS, self::RECIPES]);

        self::assertSame(['categories' => [self::RECIPES, self::NEWS]], self::indexedFields()->in($post));
    }

    /**
     * A value of any shape may be stored, and only UUIDs refer to entries
     *
     * @test
     */
    public function it_leaves_out_what_is_not_a_uuid(): void
    {
        $post = new BlogPost();
        $post->setCategories(['news', self::NEWS]);

        self::assertSame(['categories' => [self::NEWS]], self::indexedFields()->in($post));

        self::assertSame(['categories' => []], self::indexedFields()->in(new BlogPost()));
        self::assertSame([], self::indexedFields()->in(new Page()));
    }

    /**
     * @test
     */
    public function it_tells_the_field_that_holds_the_categories_of_a_collection(): void
    {
        $fields = self::indexedFields();

        self::assertSame('categories', $fields->categoryField(self::registry()->get('blog_post'))?->name);
        self::assertNull($fields->categoryField(self::registry()->get('blog_category')));
        self::assertNull($fields->categoryField(self::registry()->get('page')));
    }

    /**
     * Only an Entries field of the category content type holds the categories, so one that names a class no content
     * type is is passed over
     *
     * @test
     */
    public function it_passes_over_an_indexed_field_of_a_class_no_content_type_is_to_find_the_categories(): void
    {
        $registry = self::registry(['course' => ['model' => Course::class, 'translation' => EntryTranslation::class, 'resource' => 'app.course']]);
        $fields = new IndexedFields($registry, new ContentTypeFields(new FieldReader()));

        self::assertSame(['recipes', 'categories'], array_keys($fields->of($registry->get('course'))));
        self::assertSame('categories', $fields->categoryField($registry->get('course'))?->name);

        $course = new Course();
        $course->recipes = [self::RECIPES];
        $course->categories = [self::NEWS];
        self::assertSame([self::NEWS], $fields->categoriesOf($course));
    }

    /**
     * The container build refuses a collection like this, but the registry alone doesn't
     *
     * @test
     */
    public function it_finds_no_category_field_when_no_indexed_field_names_the_category_type(): void
    {
        $registry = self::registry([
            'workshop' => ['model' => Workshop::class, 'translation' => EntryTranslation::class, 'resource' => 'app.workshop'],
            'recipe' => ['model' => Recipe::class, 'translation' => EntryTranslation::class, 'resource' => 'app.recipe'],
        ]);
        $fields = new IndexedFields($registry, new ContentTypeFields(new FieldReader()));

        self::assertSame('recipe', $registry->get('workshop')->categoryType);
        self::assertNull($fields->categoryField($registry->get('workshop')));

        $workshop = new Workshop();
        $workshop->recipes = [self::RECIPES];
        self::assertSame([], $fields->categoriesOf($workshop));
    }

    /**
     * @test
     */
    public function it_reads_the_categories_of_an_entry_in_the_editors_order(): void
    {
        $post = new BlogPost();
        $post->setCategories([self::RECIPES, 'news', self::NEWS]);

        self::assertSame([self::RECIPES, self::NEWS], self::indexedFields()->categoriesOf($post));
        self::assertSame([], self::indexedFields()->categoriesOf(new BlogCategory()));
        self::assertSame([], self::indexedFields()->categoriesOf(new Page()));
    }

    private static function indexedFields(): IndexedFields
    {
        return new IndexedFields(self::registry(), new ContentTypeFields(new FieldReader()));
    }

    /**
     * @param array<string, array{model: class-string<EntryInterface>, translation: class-string|null, resource: string}> $more
     */
    private static function registry(array $more = []): ContentTypeRegistry
    {
        return new ContentTypeRegistry([
            'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
            'blog_post' => ['model' => BlogPost::class, 'translation' => BlogPostTranslation::class, 'resource' => 'setono_sylius_cms.blog_post'],
            'blog_category' => ['model' => BlogCategory::class, 'translation' => BlogCategoryTranslation::class, 'resource' => 'setono_sylius_cms.blog_category'],
        ] + $more);
    }
}
