<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Condition\Kind;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Attribute\NoParams;
use Setono\SyliusCMSPlugin\Collection\IndexedFields;
use Setono\SyliusCMSPlugin\Condition\Kind\EntryInCategoryKind;
use Setono\SyliusCMSPlugin\Condition\Kind\EntryInCategoryParams;
use Setono\SyliusCMSPlugin\Model\BlogCategory;
use Setono\SyliusCMSPlugin\Model\BlogCategoryTranslation;
use Setono\SyliusCMSPlugin\Model\BlogPost;
use Setono\SyliusCMSPlugin\Model\BlogPostTranslation;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Registry\ContentTypeFields;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;
use Setono\SyliusCMSPlugin\Value\ItemList;
use Sylius\Component\Core\Model\Product;

/**
 * The Entry in category condition kind: it matches an entry whose content type has categories and which is in any of
 * the condition's categories. See "Condition kinds" in docs/spec/targeting.md
 */
final class EntryInCategoryKindTest extends TestCase
{
    /**
     * @test
     */
    public function it_matches_an_entry_in_any_of_the_categories(): void
    {
        $news = new BlogCategory();
        $recipes = new BlogCategory();
        $events = new BlogCategory();

        $params = self::params($news, $events);

        self::assertTrue(self::kind()->matches($params, self::showing(self::post($recipes, $events))));
        self::assertFalse(self::kind()->matches($params, self::showing(self::post($recipes))));
        self::assertFalse(self::kind()->matches($params, self::showing(self::post())));
    }

    /**
     * @test
     */
    public function it_matches_no_entry_whose_content_type_has_no_categories(): void
    {
        $news = new BlogCategory();

        self::assertFalse(self::kind()->matches(self::params($news), self::showing(new Page())));
        self::assertFalse(self::kind()->matches(self::params($news), self::showing($news)), 'A category isn\'t in itself');
    }

    /**
     * The categories hydrate as those the shopper may see, so none is a condition that matches nothing
     *
     * @test
     */
    public function it_matches_nothing_without_categories(): void
    {
        $post = self::post(new BlogCategory());

        self::assertFalse(self::kind()->matches(self::params(), self::showing($post)));
        self::assertFalse(self::kind()->matches(new EntryInCategoryParams(), self::showing($post)));
    }

    /**
     * @test
     */
    public function it_matches_nothing_where_no_entry_is_shown(): void
    {
        $news = new BlogCategory();

        self::assertFalse(self::kind()->matches(self::params($news), Rendering::context()));
        self::assertFalse(self::kind()->matches(self::params($news), Rendering::context(hostPageContext: ['entry' => new Product()])), 'Not an entry');
    }

    /**
     * @test
     */
    public function it_takes_only_its_own_parameters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('The entry in category condition takes %s, got %s', EntryInCategoryParams::class, NoParams::class));

        self::kind()->matches(new NoParams(), self::showing(new Page()));
    }

    private static function kind(): EntryInCategoryKind
    {
        return new EntryInCategoryKind(new IndexedFields(new ContentTypeRegistry([
            'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
            'blog_post' => ['model' => BlogPost::class, 'translation' => BlogPostTranslation::class, 'resource' => 'setono_sylius_cms.blog_post'],
            'blog_category' => ['model' => BlogCategory::class, 'translation' => BlogCategoryTranslation::class, 'resource' => 'setono_sylius_cms.blog_category'],
        ]), new ContentTypeFields(new FieldReader())));
    }

    private static function post(BlogCategory ...$categories): BlogPost
    {
        $post = new BlogPost();
        $post->setCategories(array_values(array_map(static fn (BlogCategory $category): string => $category->getUuid(), $categories)));

        return $post;
    }

    private static function params(EntryInterface ...$categories): EntryInCategoryParams
    {
        $params = new EntryInCategoryParams();
        $params->categories = ItemList::of(array_values($categories));

        return $params;
    }

    private static function showing(EntryInterface $entry): RenderContext
    {
        return Rendering::context(hostPageContext: ['entry' => $entry]);
    }
}
