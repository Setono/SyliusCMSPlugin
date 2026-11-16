<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Routing;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Registry\ContentTypeMetadata;
use Setono\SyliusCMSPlugin\Routing\EntryPath;

final class EntryPathTest extends TestCase
{
    /**
     * @test
     */
    public function it_leaves_out_the_segment_of_an_empty_prefix(): void
    {
        self::assertSame('about/team', EntryPath::of(self::contentType(''), self::page('about/team'), 'en_US'));
    }

    /**
     * @test
     */
    public function it_puts_a_prefix_before_the_slug(): void
    {
        self::assertSame('blog/hello', EntryPath::of(self::contentType('blog'), self::page('hello'), 'en_US'));
    }

    /**
     * @test
     */
    public function it_takes_the_slug_of_the_locale(): void
    {
        $page = self::page('about-us');
        $page->setCurrentLocale('da_DK');
        $page->setSlug('om-os');

        self::assertSame('om-os', EntryPath::of(self::contentType(''), $page, 'da_DK'));
        self::assertSame('about-us', EntryPath::of(self::contentType(''), $page, 'en_US'));
    }

    /**
     * @test
     */
    public function it_gives_no_path_to_a_content_type_without_a_url_of_its_own(): void
    {
        self::assertNull(EntryPath::of(self::contentType(null), self::page('about-us'), 'en_US'));
    }

    /**
     * @test
     */
    public function it_gives_no_path_to_an_entry_without_a_translation_in_the_locale(): void
    {
        $page = self::page('about-us');
        $page->setFallbackLocale('en_US');

        self::assertNull(EntryPath::of(self::contentType(''), $page, 'da_DK'));
    }

    /**
     * @test
     */
    public function it_gives_no_path_to_an_entry_without_a_slug(): void
    {
        self::assertNull(EntryPath::of(self::contentType(''), self::page(null), 'en_US'));
        self::assertNull(EntryPath::of(self::contentType('blog'), self::page(''), 'en_US'));
    }

    /**
     * @test
     */
    public function it_builds_the_path_of_a_slug_read_from_elsewhere_such_as_a_draft(): void
    {
        self::assertSame('blog/hello', EntryPath::ofSlug(self::contentType('blog'), 'hello'));
        self::assertSame('hello', EntryPath::ofSlug(self::contentType(''), 'hello'));
        self::assertNull(EntryPath::ofSlug(self::contentType(null), 'hello'));
        self::assertNull(EntryPath::ofSlug(self::contentType('blog'), null));
        self::assertNull(EntryPath::ofSlug(self::contentType('blog'), ''));
    }

    /**
     * @test
     */
    public function it_finds_the_slug_a_path_holds_for_each_content_type_whose_prefix_fits_it(): void
    {
        $contentTypes = [
            self::contentType('', 'page'),
            self::contentType('blog', 'blog_post'),
            self::contentType('blog/category', 'blog_category'),
            self::contentType(null, 'snippet'),
        ];

        self::assertSame(['page' => 'about-us'], EntryPath::slugs($contentTypes, 'about-us'));
        self::assertSame(['page' => 'blog/hello', 'blog_post' => 'hello'], EntryPath::slugs($contentTypes, 'blog/hello'));
        self::assertSame(
            ['page' => 'blog/category/news', 'blog_post' => 'category/news', 'blog_category' => 'news'],
            EntryPath::slugs($contentTypes, 'blog/category/news'),
        );
    }

    /**
     * @test
     */
    public function a_path_that_is_only_the_prefix_holds_no_slug_under_it(): void
    {
        self::assertSame([], EntryPath::slugs([self::contentType('blog', 'blog_post')], 'blog'));
        self::assertSame([], EntryPath::slugs([self::contentType('blog', 'blog_post')], 'blog/'));
        self::assertSame([], EntryPath::slugs([self::contentType('blog', 'blog_post')], 'blogs/hello'));
    }

    private static function contentType(?string $prefix, string $code = 'page'): ContentTypeMetadata
    {
        return new ContentTypeMetadata($code, 'Page', 1, $prefix, null, Page::class, PageTranslation::class, 'setono_sylius_cms.page');
    }

    private static function page(?string $slug): Page
    {
        $page = new Page();
        $page->setCurrentLocale('en_US');
        $page->setSlug($slug);

        return $page;
    }
}
