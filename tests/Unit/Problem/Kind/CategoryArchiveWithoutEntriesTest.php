<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Problem\Kind;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Collection\Collections;
use Setono\SyliusCMSPlugin\Model\BlogCategory;
use Setono\SyliusCMSPlugin\Model\BlogCategoryTranslation;
use Setono\SyliusCMSPlugin\Model\BlogPost;
use Setono\SyliusCMSPlugin\Model\BlogPostTranslation;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\FaqCategory;
use Setono\SyliusCMSPlugin\Model\FaqItem;
use Setono\SyliusCMSPlugin\Model\FaqItemTranslation;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\Kind\CategoryArchiveWithoutEntries;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A category's URL is its archive, which lists the category's entries through an Entries block in its tree. Editors
 * can remove it, and the admin then warns. See "Listings" in docs/spec/collections.md and "Problems" in
 * docs/spec/admin.md
 */
final class CategoryArchiveWithoutEntriesTest extends TestCase
{
    /**
     * @test
     */
    public function it_finds_a_category_whose_tree_holds_no_entries_block_as_a_warning_fixed_in_the_builder(): void
    {
        $category = self::withBlocks(new BlogCategory(), [self::block('heading'), self::block('rich_text')]);

        self::assertEquals([new Problem(
            'category_archive_without_entries',
            Severity::Warning,
            $category,
            new TranslatableMessage('setono_sylius_cms.problem.category_archive_without_entries', ['%source%' => new TranslatableMessage('setono_sylius_cms.source.entries_in_this_category')]),
            Fix::inBuilder($category),
        )], [...self::kind()->findIn($category)]);
        self::assertCount(1, [...self::kind()->findIn(self::withBlocks(new BlogCategory(), []))], 'An empty tree holds none either');
    }

    /**
     * Wherever it is, and however it's filled, as an editor may list the category's entries another way
     *
     * @test
     */
    public function it_finds_nothing_in_a_category_whose_tree_holds_an_entries_block(): void
    {
        $nested = self::block('columns', [self::block('column', [self::block('heading')]), self::block('column', [self::block('entries')])]);

        self::assertSame([], [...self::kind()->findIn(self::withBlocks(new BlogCategory(), [self::block('heading'), self::block('entries')]))]);
        self::assertSame([], [...self::kind()->findIn(self::withBlocks(new BlogCategory(), [$nested]))]);
    }

    /**
     * A category without its own URL has no archive, as FAQ categories don't, and other entries list what they like
     *
     * @test
     */
    public function it_finds_nothing_in_a_category_without_an_archive_or_in_an_entry_that_is_not_a_category(): void
    {
        self::assertSame([], [...self::kind()->findIn(new FaqCategory())]);
        self::assertSame([], [...self::kind()->findIn(self::withBlocks(new BlogPost(), [self::block('heading')]))]);
        self::assertSame([], [...self::kind()->findIn(self::withBlocks(new Page(), [self::block('heading')]))]);
    }

    private static function kind(): CategoryArchiveWithoutEntries
    {
        $contentTypes = new ContentTypeRegistry([
            'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
            'blog_post' => ['model' => BlogPost::class, 'translation' => BlogPostTranslation::class, 'resource' => 'setono_sylius_cms.blog_post'],
            'blog_category' => ['model' => BlogCategory::class, 'translation' => BlogCategoryTranslation::class, 'resource' => 'setono_sylius_cms.blog_category'],
            'faq_item' => ['model' => FaqItem::class, 'translation' => FaqItemTranslation::class, 'resource' => 'setono_sylius_cms.faq_item'],
            'faq_category' => ['model' => FaqCategory::class, 'translation' => EntryTranslation::class, 'resource' => 'setono_sylius_cms.faq_category'],
        ]);

        return new CategoryArchiveWithoutEntries($contentTypes, new Collections($contentTypes));
    }

    /**
     * @template T of EntryInterface
     *
     * @param T $entry
     * @param list<StoredBlock> $blocks
     *
     * @return T
     */
    private static function withBlocks(EntryInterface $entry, array $blocks): EntryInterface
    {
        $entry->setBlockTree(new StoredTree(null, $blocks));

        return $entry;
    }

    /**
     * @param list<StoredBlock> $children
     */
    private static function block(string $type, array $children = []): StoredBlock
    {
        return new StoredBlock(sprintf('0193a5be-0000-7000-8000-%012d', random_int(1, 999999)), $type, 1, children: $children);
    }
}
