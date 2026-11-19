<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Model\BlogCategory;
use Setono\SyliusCMSPlugin\Model\BlogCategoryTranslation;
use Setono\SyliusCMSPlugin\Model\BlogPost;
use Setono\SyliusCMSPlugin\Model\BlogPostTranslation;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\SourceMetadata;
use Setono\SyliusCMSPlugin\Source\SourceInterface;
use Setono\SyliusCMSPlugin\Tests\Unit\Context\Fixture\CategoryPosts;
use Setono\SyliusCMSPlugin\Tests\Unit\Context\Fixture\Neighbours;

/**
 * A source as its #[AsSource] declares it. See "Entry sources" in docs/spec/sources.md
 */
final class SourceMetadataTest extends TestCase
{
    /**
     * A source such as In this category fits only some content types as the host page's entry, which the builder and
     * publish hold an entry's content type against
     *
     * @test
     */
    public function it_gives_the_content_types_whose_entries_it_fits_as_the_entry_or_null_when_it_fits_any(): void
    {
        $contentTypes = (new ContentTypeRegistry([
            'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
            'blog_post' => ['model' => BlogPost::class, 'translation' => BlogPostTranslation::class, 'resource' => 'setono_sylius_cms.blog_post'],
            'blog_category' => ['model' => BlogCategory::class, 'translation' => BlogCategoryTranslation::class, 'resource' => 'setono_sylius_cms.blog_category'],
        ]))->all();

        self::assertSame(['blog_category'], self::source(new CategoryPosts())->entryContentTypes($contentTypes));
        self::assertSame([], self::source(new CategoryPosts())->entryContentTypes([]));
        self::assertNull(self::source(new Neighbours())->entryContentTypes($contentTypes));
    }

    private static function source(SourceInterface $source): SourceMetadata
    {
        return new SourceMetadata('app.source', 'app.source', EntryInterface::class, ['entry'], null, [], $source);
    }
}
