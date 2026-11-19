<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Model\BlogCategory;
use Setono\SyliusCMSPlugin\Model\BlogCategoryInterface;
use Setono\SyliusCMSPlugin\Model\BlogPost;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Registry\ContentTypeModels;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\SeasonalRecipe;

/**
 * A class names a content type, as a categoryType or an Entries field's contentType does, by its model or a class or
 * interface the model extends
 */
final class ContentTypeModelsTest extends TestCase
{
    /**
     * @test
     */
    public function it_finds_the_model_that_is_the_class(): void
    {
        self::assertSame(BlogCategory::class, ContentTypeModels::namedBy(BlogCategory::class, ['page' => Page::class, 'blog_category' => BlogCategory::class]));
    }

    /**
     * A project's model of a shipped content type extends the shipped model, which still names it
     *
     * @test
     */
    public function it_finds_the_first_model_that_extends_or_implements_the_class_when_none_is_it(): void
    {
        self::assertSame(SeasonalRecipe::class, ContentTypeModels::namedBy(Recipe::class, ['page' => Page::class, 'recipe' => SeasonalRecipe::class]));
        self::assertSame(BlogCategory::class, ContentTypeModels::namedBy(BlogCategoryInterface::class, ['blog_post' => BlogPost::class, 'blog_category' => BlogCategory::class]));
    }

    /**
     * @test
     */
    public function it_prefers_the_model_that_is_the_class_over_one_that_extends_it(): void
    {
        self::assertSame(Recipe::class, ContentTypeModels::namedBy(Recipe::class, ['seasonal' => SeasonalRecipe::class, 'recipe' => Recipe::class]));
    }

    /**
     * @test
     */
    public function it_finds_none_when_no_model_is_or_extends_the_class(): void
    {
        self::assertNull(ContentTypeModels::namedBy(BlogCategory::class, ['page' => Page::class]));
    }

    /**
     * @test
     */
    public function it_tells_the_code_of_the_content_type_whose_model_it_finds(): void
    {
        self::assertSame('recipe', ContentTypeModels::codeNamedBy(Recipe::class, ['page' => Page::class, 'recipe' => SeasonalRecipe::class]));
        self::assertNull(ContentTypeModels::codeNamedBy(BlogCategory::class, ['page' => Page::class]));
    }
}
