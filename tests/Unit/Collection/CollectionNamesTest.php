<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Collection;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Collection\CollectionMetadata;
use Setono\SyliusCMSPlugin\Collection\CollectionNames;
use Setono\SyliusCMSPlugin\Model\BlogCategory;
use Setono\SyliusCMSPlugin\Model\BlogCategoryTranslation;
use Setono\SyliusCMSPlugin\Model\BlogPost;
use Setono\SyliusCMSPlugin\Model\BlogPostTranslation;
use Setono\SyliusCMSPlugin\Registry\ContentTypeMetadata;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Shop;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\ShopTranslation;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

/**
 * How the admin and the builder name a collection, a content type's list and an Entries display: by the first of their
 * translation keys that has a translation, or else by a label. See "Navigation" in docs/spec/admin.md
 */
final class CollectionNamesTest extends TestCase
{
    /**
     * @test
     */
    public function it_names_a_collection_by_its_name_or_else_by_its_entries_plural_or_else_by_their_label(): void
    {
        $names = new CollectionNames(self::translator());

        self::assertSame('Blog', $names->collection(new CollectionMetadata(self::contentType('blog_post'), self::contentType('blog_category'))));
        self::assertSame('Shops', $names->collection(new CollectionMetadata(self::contentType('shop'))));
        self::assertSame('Recipe', $names->collection(new CollectionMetadata(self::contentType('recipe'))));
    }

    /**
     * @test
     */
    public function it_names_a_content_types_list_by_its_plural_or_else_by_its_label(): void
    {
        $names = new CollectionNames(self::translator());

        self::assertSame('Posts', $names->plural(self::contentType('blog_post')));
        self::assertSame('Categories', $names->plural(self::contentType('blog_category')));
        self::assertSame('Recipe', $names->plural(self::contentType('recipe')));
    }

    /**
     * @test
     */
    public function it_names_an_entries_display_by_its_translation_or_else_by_the_display_itself(): void
    {
        $names = new CollectionNames(self::translator());

        self::assertSame('Grid', $names->display('grid'));
        self::assertSame('Big grid', $names->display('Big grid'));
    }

    /**
     * @test
     */
    public function it_names_them_in_the_locale_asked_for_falling_back_as_the_translator_does(): void
    {
        $names = new CollectionNames(self::translator());

        self::assertSame('Blogg', $names->collection(new CollectionMetadata(self::contentType('blog_post')), 'da_DK'));
        self::assertSame('Indlæg', $names->plural(self::contentType('blog_post'), 'da_DK'));
        self::assertSame('Gitter', $names->display('grid', 'da_DK'));
        self::assertSame('Shops', $names->collection(new CollectionMetadata(self::contentType('shop')), 'da_DK'));
        self::assertSame('Opskrift', $names->collection(new CollectionMetadata(self::contentType('recipe')), 'da_DK'));
    }

    private static function translator(): Translator
    {
        $translator = new Translator('en');
        $translator->setFallbackLocales(['en']);
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', [
            'setono_sylius_cms.collection.blog_post' => 'Blog',
            'setono_sylius_cms.content_type_plural.blog_post' => 'Posts',
            'setono_sylius_cms.content_type_plural.blog_category' => 'Categories',
            'setono_sylius_cms.content_type_plural.shop' => 'Shops',
            'setono_sylius_cms.choice.entries_display.grid' => 'Grid',
            'app.recipe' => 'Recipe',
        ], 'en');
        $translator->addResource('array', [
            'setono_sylius_cms.collection.blog_post' => 'Blogg',
            'setono_sylius_cms.content_type_plural.blog_post' => 'Indlæg',
            'setono_sylius_cms.choice.entries_display.grid' => 'Gitter',
            'app.recipe' => 'Opskrift',
        ], 'da');

        return $translator;
    }

    private static function contentType(string $code): ContentTypeMetadata
    {
        return (new ContentTypeRegistry([
            'recipe' => ['model' => Recipe::class, 'translation' => null, 'resource' => 'app.recipe'],
            'shop' => ['model' => Shop::class, 'translation' => ShopTranslation::class, 'resource' => 'app.shop'],
            'blog_post' => ['model' => BlogPost::class, 'translation' => BlogPostTranslation::class, 'resource' => 'setono_sylius_cms.blog_post'],
            'blog_category' => ['model' => BlogCategory::class, 'translation' => BlogCategoryTranslation::class, 'resource' => 'setono_sylius_cms.blog_category'],
        ]))->get($code);
    }
}
