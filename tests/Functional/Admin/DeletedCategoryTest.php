<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin;

use Setono\SyliusCMSPlugin\Model\BlogCategoryInterface;
use Setono\SyliusCMSPlugin\Problem\Kind\BrokenReference;
use Setono\SyliusCMSPlugin\Problem\ProblemCount;
use Setono\SyliusCMSPlugin\Problem\ProblemFinderInterface;
use Setono\SyliusCMSPlugin\Publishing\EntryDeleterInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Blog;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A deleted category leaves dangling UUIDs on its entries, which the admin flags as broken references, counted again as
 * soon as the category is deleted. See "Categories" in docs/spec/collections.md and "Problems" in docs/spec/admin.md
 */
final class DeletedCategoryTest extends KernelTestCase
{
    use Blog;
    use Entries;

    /**
     * @test
     */
    public function it_flags_the_published_posts_in_a_category_that_was_deleted(): void
    {
        $news = self::createBlogCategory('News', 'news', published: true);
        $recipes = self::createBlogCategory('Recipes', 'recipes', published: true);
        $inNews = self::createBlogPost('Opening night', 'opening-night', [$recipes, $news], published: true);
        $inRecipes = self::createBlogPost('Soup', 'soup', [$recipes], published: true);
        self::assertEquals(new ProblemCount(), self::storedCount($inNews));

        self::delete($news);

        self::assertEquals(new ProblemCount(1, 0), self::storedCount($inNews));
        self::assertEquals(new ProblemCount(), self::storedCount($inRecipes));

        $problems = [...self::problemFinder()->findByEntry($inNews)];
        self::assertCount(1, $problems);
        self::assertSame(BrokenReference::CODE, $problems[0]->kind);
        self::assertSame('categories', $problems[0]->field);
        self::assertNull($problems[0]->block);
        self::assertSame('Categories refers to an entry that was deleted, so it isn\'t shown.', $problems[0]->message->trans(self::translator()));
    }

    /**
     * A draft's categories live in its draft, which is searched too
     *
     * @test
     */
    public function it_flags_a_post_whose_draft_is_in_a_category_that_was_deleted(): void
    {
        $news = self::createBlogCategory('News', 'news', published: true);
        $post = self::createBlogPost('Opening night', 'opening-night', published: true);
        self::saveChanges($post, [['op' => 'set_entry_value', 'field' => 'categories', 'locale' => null, 'value' => [$news->getUuid()]]]);

        self::delete($news);

        self::assertEquals(new ProblemCount(1, 0), self::storedCount($post));
    }

    private static function delete(BlogCategoryInterface $category): void
    {
        /** @var EntryDeleterInterface $deleter */
        $deleter = self::getContainer()->get(EntryDeleterInterface::class);
        $deleter->delete($category);
    }

    private static function problemFinder(): ProblemFinderInterface
    {
        /** @var ProblemFinderInterface $finder */
        $finder = self::getContainer()->get(ProblemFinderInterface::class);

        return $finder;
    }

    private static function translator(): TranslatorInterface
    {
        /** @var TranslatorInterface $translator */
        $translator = self::getContainer()->get('translator');

        return $translator;
    }
}
