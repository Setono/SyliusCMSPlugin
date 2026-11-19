<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Admin\EntryCategories;
use Setono\SyliusCMSPlugin\Collection\IndexedFields;
use Setono\SyliusCMSPlugin\Model\BlogCategory;
use Setono\SyliusCMSPlugin\Model\BlogCategoryTranslation;
use Setono\SyliusCMSPlugin\Model\BlogPost;
use Setono\SyliusCMSPlugin\Model\BlogPostTranslation;
use Setono\SyliusCMSPlugin\Model\DraftInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Registry\ContentTypeFields;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;

/**
 * See "Entry lists" in docs/spec/admin.md
 */
final class EntryCategoriesTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_names_the_categories_an_entry_holds_in_the_first_locale_each_has_in_the_editors_order(): void
    {
        $news = self::category(['en_US' => 'News', 'da_DK' => 'Nyheder']);
        $guides = self::category(['en_US' => 'Guides']);
        $post = new BlogPost();
        $post->setCategories([$news->getUuid(), $guides->getUuid()]);
        $page = new Page();

        $titles = $this->categories([$news, $guides])->titlesOf([$post, $page], [], ['da_DK', 'en_US']);

        self::assertSame([$post->getUuid() => ['Nyheder', 'Guides']], $titles, 'A page has no categories');
    }

    /**
     * A draft is the working copy, which the lists show
     *
     * @test
     */
    public function it_reads_the_categories_of_an_entry_with_a_draft_from_the_drafts_json(): void
    {
        $news = self::category(['en_US' => 'News']);
        $guides = self::category(['en_US' => 'Guides']);
        $post = new BlogPost();
        $post->setCategories([$news->getUuid()]);
        $draft = $this->prophesize(DraftInterface::class);
        $draft->getContent()->willReturn(json_encode(['fields' => ['categories' => [strtoupper($guides->getUuid()), 'not a UUID']], 'translations' => new \stdClass()], \JSON_THROW_ON_ERROR));

        $titles = $this->categories([$guides])->titlesOf([$post], [$post->getUuid() => $draft->reveal()], ['en_US']);

        self::assertSame([$post->getUuid() => ['Guides']], $titles);
    }

    /**
     * @test
     */
    public function it_leaves_out_a_category_that_no_longer_exists_and_asks_for_nothing_without_categories(): void
    {
        $post = new BlogPost();
        $post->setCategories(['0193a5be-0000-7000-8000-000000000001']);

        self::assertSame([$post->getUuid() => []], $this->categories([])->titlesOf([$post], [], ['en_US']));

        $uncategorised = new BlogPost();
        $entries = $this->prophesize(EntryRepositoryInterface::class);
        $entries->findByUuids(Argument::any())->shouldNotBeCalled();
        self::assertSame([$uncategorised->getUuid() => []], self::entryCategories($entries->reveal())->titlesOf([$uncategorised], [], ['en_US']));
    }

    /**
     * A draft whose JSON can't be read holds no categories the lists can show, rather than the published ones
     *
     * @test
     */
    public function it_shows_no_categories_for_an_entry_whose_draft_is_not_valid_json(): void
    {
        $news = self::category(['en_US' => 'News']);
        $post = new BlogPost();
        $post->setCategories([$news->getUuid()]);
        $draft = $this->prophesize(DraftInterface::class);
        $draft->getContent()->willReturn('{"fields": {"categories": [');

        $entries = $this->prophesize(EntryRepositoryInterface::class);
        $entries->findByUuids(Argument::any())->shouldNotBeCalled();

        self::assertSame([$post->getUuid() => []], self::entryCategories($entries->reveal())->titlesOf([$post], [$post->getUuid() => $draft->reveal()], ['en_US']));
    }

    /**
     * @test
     */
    public function it_names_a_category_in_its_first_locale_when_it_has_none_of_the_asked_ones_and_by_its_uuid_when_it_has_no_title(): void
    {
        $nyheder = self::category(['da_DK' => 'Nyheder', 'fr_FR' => 'Nouvelles']);
        $untitled = self::category(['en_US' => '']);
        $post = new BlogPost();
        $post->setCategories([$nyheder->getUuid(), $untitled->getUuid()]);

        $titles = $this->categories([$nyheder, $untitled])->titlesOf([$post], [], ['en_US']);

        self::assertSame([$post->getUuid() => ['Nyheder', $untitled->getUuid()]], $titles);
    }

    /**
     * @param list<BlogCategory> $existing
     */
    private function categories(array $existing): EntryCategories
    {
        $entries = $this->prophesize(EntryRepositoryInterface::class);
        $entries->findByUuids(Argument::type('array'))->willReturn($existing);

        return self::entryCategories($entries->reveal());
    }

    private static function entryCategories(EntryRepositoryInterface $entries): EntryCategories
    {
        $contentTypes = new ContentTypeRegistry([
            'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
            'blog_post' => ['model' => BlogPost::class, 'translation' => BlogPostTranslation::class, 'resource' => 'setono_sylius_cms.blog_post'],
            'blog_category' => ['model' => BlogCategory::class, 'translation' => BlogCategoryTranslation::class, 'resource' => 'setono_sylius_cms.blog_category'],
        ]);

        return new EntryCategories(new IndexedFields($contentTypes, new ContentTypeFields(new FieldReader())), $contentTypes, $entries);
    }

    /**
     * @param array<string, string> $titles by locale
     */
    private static function category(array $titles): BlogCategory
    {
        $category = new BlogCategory();
        foreach ($titles as $localeCode => $title) {
            $category->setCurrentLocale($localeCode);
            $category->setFallbackLocale($localeCode);
            $category->setTitle($title);
        }

        return $category;
    }
}
