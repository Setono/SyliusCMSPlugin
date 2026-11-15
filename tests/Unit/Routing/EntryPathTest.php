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

    private static function contentType(?string $prefix): ContentTypeMetadata
    {
        return new ContentTypeMetadata('page', 'Page', 1, $prefix, null, Page::class, PageTranslation::class, 'setono_sylius_cms.page');
    }

    private static function page(?string $slug): Page
    {
        $page = new Page();
        $page->setCurrentLocale('en_US');
        $page->setSlug($slug);

        return $page;
    }
}
