<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Collection;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Collection\IndexedFields;
use Setono\SyliusCMSPlugin\Model\BlogCategory;
use Setono\SyliusCMSPlugin\Model\BlogCategoryTranslation;
use Setono\SyliusCMSPlugin\Model\BlogPost;
use Setono\SyliusCMSPlugin\Model\BlogPostTranslation;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Registry\ContentTypeFields;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;

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

    private static function registry(): ContentTypeRegistry
    {
        return new ContentTypeRegistry([
            'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
            'blog_post' => ['model' => BlogPost::class, 'translation' => BlogPostTranslation::class, 'resource' => 'setono_sylius_cms.blog_post'],
            'blog_category' => ['model' => BlogCategory::class, 'translation' => BlogCategoryTranslation::class, 'resource' => 'setono_sylius_cms.blog_category'],
        ]);
    }
}
