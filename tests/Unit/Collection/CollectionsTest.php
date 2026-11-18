<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Collection;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Collection\CollectionMetadata;
use Setono\SyliusCMSPlugin\Collection\Collections;
use Setono\SyliusCMSPlugin\Model\BlogCategory;
use Setono\SyliusCMSPlugin\Model\BlogCategoryTranslation;
use Setono\SyliusCMSPlugin\Model\BlogPost;
use Setono\SyliusCMSPlugin\Model\BlogPostTranslation;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Shop;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\ShopTranslation;

/**
 * The collections: all entries of a content type other than the built-in ones, with their categories, a content type
 * of their own, which belongs to its collection. See "Collection" and "Category" in GLOSSARY.md
 */
final class CollectionsTest extends TestCase
{
    /**
     * @test
     */
    public function it_lists_the_collections_the_shipped_ones_first_each_with_its_categories(): void
    {
        $collections = self::collections()->all();

        self::assertSame([['blog_post', 'blog_category'], ['shop', null]], array_map(
            static fn (CollectionMetadata $collection): array => [$collection->entries->code, $collection->categories?->code],
            $collections,
        ));
        self::assertSame('blog_post', $collections[0]->code());
        self::assertTrue($collections[0]->hasCategories());
        self::assertFalse($collections[1]->hasCategories());
    }

    /**
     * @test
     */
    public function it_finds_the_collection_of_its_entries_and_of_its_categories(): void
    {
        $collections = self::collections();

        self::assertSame('blog_post', $collections->of('blog_post')?->code());
        self::assertSame('blog_post', $collections->of('blog_category')?->code());
        self::assertSame('shop', $collections->of('shop')?->code());
        self::assertNull($collections->of('page'));
        self::assertNull($collections->of('snippet'));
        self::assertNull($collections->of('recipe'), 'A disabled content type is no collection');
        self::assertNull($collections->of('faq_item'));
    }

    /**
     * @test
     */
    public function it_tells_a_collection_with_categories_by_the_code_of_its_entries(): void
    {
        $collections = self::collections();

        self::assertTrue($collections->hasCategories('blog_post'));
        self::assertFalse($collections->hasCategories('blog_category'));
        self::assertFalse($collections->hasCategories('shop'));
        self::assertFalse($collections->hasCategories('page'));
        self::assertFalse($collections->hasCategories('unknown'));
    }

    /**
     * A collection whose category content type is switched off is listed without its categories
     *
     * @test
     */
    public function it_leaves_out_categories_that_are_switched_off(): void
    {
        $collections = new Collections(new ContentTypeRegistry(self::resources(), disabled: ['blog_category']));

        self::assertNull($collections->of('blog_post')?->categories);
        self::assertNull($collections->of('blog_category'));
    }

    private static function collections(): Collections
    {
        return new Collections(new ContentTypeRegistry(self::resources(), disabled: ['recipe']));
    }

    /**
     * @return array<string, array{model: class-string<\Setono\SyliusCMSPlugin\Model\EntryInterface>, translation: class-string|null, resource: string}>
     */
    private static function resources(): array
    {
        return [
            'shop' => ['model' => Shop::class, 'translation' => ShopTranslation::class, 'resource' => 'app.shop'],
            'recipe' => ['model' => Recipe::class, 'translation' => null, 'resource' => 'app.recipe'],
            'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
            'snippet' => ['model' => Snippet::class, 'translation' => null, 'resource' => 'setono_sylius_cms.snippet'],
            'blog_post' => ['model' => BlogPost::class, 'translation' => BlogPostTranslation::class, 'resource' => 'setono_sylius_cms.blog_post'],
            'blog_category' => ['model' => BlogCategory::class, 'translation' => BlogCategoryTranslation::class, 'resource' => 'setono_sylius_cms.blog_category'],
        ];
    }
}
