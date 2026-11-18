<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Model\BlogCategory;
use Setono\SyliusCMSPlugin\Model\BlogPost;
use Setono\SyliusCMSPlugin\Model\BlogPostTranslation;
use Setono\SyliusCMSPlugin\Registry\AttributeReader;

final class BlogPostTest extends TestCase
{
    /**
     * @test
     */
    public function it_is_the_blog_post_content_type_under_blog_ordered_newest_first_with_blog_categories(): void
    {
        $contentType = AttributeReader::contentType(BlogPost::class);

        self::assertNotNull($contentType);
        self::assertSame('blog_post', $contentType->code);
        self::assertSame('setono_sylius_cms.content_type.blog_post', $contentType->label);
        self::assertSame('blog', $contentType->prefix);
        self::assertSame('*', ($contentType->blocks ?? [])[0]->type ?? null);
        self::assertSame(['publishedAt' => 'DESC'], $contentType->order);
        self::assertSame(BlogCategory::class, $contentType->categoryType);
        self::assertSame('excerpt', $contentType->descriptionField);
    }

    /**
     * The first publish fills the date when it's empty (docs/spec/collections.md#the-blog-posts-date)
     *
     * @test
     */
    public function it_takes_the_time_of_its_first_publish_as_its_date(): void
    {
        $post = new BlogPost();
        self::assertNull($post->getPublishedAt());

        $post->fillOnPublish(new \DateTimeImmutable('2026-11-13 09:00:00', new \DateTimeZone('UTC')));

        self::assertEquals(new \DateTimeImmutable('2026-11-13 09:00:00', new \DateTimeZone('UTC')), $post->getPublishedAt());
        self::assertSame('UTC', $post->getPublishedAt()?->getTimezone()->getName());
    }

    /**
     * @test
     */
    public function it_keeps_its_date_when_it_is_published_again(): void
    {
        $post = new BlogPost();
        $post->fillOnPublish(new \DateTimeImmutable('2026-11-13 09:00:00', new \DateTimeZone('UTC')));

        $post->fillOnPublish(new \DateTimeImmutable('2026-11-20 12:00:00', new \DateTimeZone('UTC')));

        self::assertEquals(new \DateTimeImmutable('2026-11-13 09:00:00', new \DateTimeZone('UTC')), $post->getPublishedAt());
    }

    /**
     * Editors can change the date, such as to backdate a post, and a publish keeps it
     *
     * @test
     */
    public function it_keeps_a_date_an_editor_set_before_its_first_publish(): void
    {
        $post = new BlogPost();
        $post->setPublishedAt(new \DateTimeImmutable('2024-03-01 08:00:00', new \DateTimeZone('UTC')));

        $post->fillOnPublish(new \DateTimeImmutable('2026-11-13 09:00:00', new \DateTimeZone('UTC')));

        self::assertEquals(new \DateTimeImmutable('2024-03-01 08:00:00', new \DateTimeZone('UTC')), $post->getPublishedAt());
    }

    /**
     * @test
     */
    public function it_holds_its_featured_image_and_its_categories_by_uuid_in_the_editors_order(): void
    {
        $post = new BlogPost();
        self::assertNull($post->getFeaturedImage());
        self::assertSame([], $post->getCategories());

        $post->setFeaturedImage('0193a5be-70aa-7c11-8e2d-1b2c3d4e5f60');
        $post->setCategories(['0193a5be-7255-7f00-8a1b-2c3d4e5f6a7c', '0193a5be-7255-7f00-8a1b-2c3d4e5f6a7b']);

        self::assertSame('0193a5be-70aa-7c11-8e2d-1b2c3d4e5f60', $post->getFeaturedImage());
        self::assertSame(['0193a5be-7255-7f00-8a1b-2c3d4e5f6a7c', '0193a5be-7255-7f00-8a1b-2c3d4e5f6a7b'], $post->getCategories());
    }

    /**
     * @test
     */
    public function it_keeps_its_excerpt_on_its_translations(): void
    {
        $post = new BlogPost();
        $post->setCurrentLocale('en_US');
        $post->setFallbackLocale('en_US');

        $translation = $post->getTranslation('en_US');
        self::assertInstanceOf(BlogPostTranslation::class, $translation);

        $translation->setExcerpt('From office to weekend.');

        self::assertSame('From office to weekend.', $translation->getExcerpt());
    }

    /**
     * @test
     */
    public function it_starts_with_an_empty_tree_in_the_article_layout(): void
    {
        $tree = (new BlogPost())->getBlockTree();

        self::assertSame('article', $tree->layout?->code);
        self::assertSame([], $tree->blocks);
    }
}
