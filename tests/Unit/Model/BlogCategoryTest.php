<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Model\BlogCategory;
use Setono\SyliusCMSPlugin\Model\BlogCategoryTranslation;
use Setono\SyliusCMSPlugin\Registry\AttributeReader;

final class BlogCategoryTest extends TestCase
{
    /**
     * @test
     */
    public function it_is_the_blog_category_content_type_under_blog_category_ordered_newest_first(): void
    {
        $contentType = AttributeReader::contentType(BlogCategory::class);

        self::assertNotNull($contentType);
        self::assertSame('blog_category', $contentType->code);
        self::assertSame('setono_sylius_cms.content_type.blog_category', $contentType->label);
        self::assertSame('blog/category', $contentType->prefix);
        self::assertSame('*', ($contentType->blocks ?? [])[0]->type ?? null);
        self::assertSame(['createdAt' => 'DESC'], $contentType->order);
        self::assertNull($contentType->categoryType);
        self::assertSame('description', $contentType->descriptionField);
    }

    /**
     * @test
     */
    public function it_keeps_its_description_on_its_translations(): void
    {
        $category = new BlogCategory();
        $category->setCurrentLocale('en_US');
        $category->setFallbackLocale('en_US');

        $translation = $category->getTranslation('en_US');
        self::assertInstanceOf(BlogCategoryTranslation::class, $translation);

        $translation->setDescription('Stories from the workshop.');

        self::assertSame('Stories from the workshop.', $translation->getDescription());
    }

    /**
     * @test
     */
    public function it_starts_with_an_empty_tree_in_the_standard_layout(): void
    {
        $tree = (new BlogCategory())->getBlockTree();

        self::assertSame('standard', $tree->layout?->code);
        self::assertSame([], $tree->blocks);
    }
}
