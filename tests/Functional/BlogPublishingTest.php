<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional;

use Setono\SyliusCMSPlugin\Model\BlogPostInterface;
use Setono\SyliusCMSPlugin\Publishing\EntryDeleterInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Blog;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * Publishing a blog post: its categories go to the reference table, which mirrors published content only, and its first
 * publish fills its date when it has none. See "Categories" and "The blog post's date" in docs/spec/collections.md
 */
final class BlogPublishingTest extends KernelTestCase
{
    use Blog;
    use ClockSensitiveTrait;
    use Entries;

    /**
     * @test
     */
    public function it_writes_the_categories_of_a_post_it_publishes_to_the_reference_table(): void
    {
        $news = self::createBlogCategory('News', 'news', published: true);
        $recipes = self::createBlogCategory('Recipes', 'recipes', published: true);

        $post = self::createBlogPost('Ten ways to style a scarf', 'ten-ways-to-style-a-scarf', [$recipes, $news]);
        self::assertSame([], self::referencesOf($post), 'A post that was never published has no rows');

        self::publishEntry($post);

        self::assertSame(self::sorted([['categories', $news->getUuid()], ['categories', $recipes->getUuid()]]), self::referencesOf($post));
    }

    /**
     * @test
     */
    public function it_keeps_the_categories_of_a_draft_out_of_the_reference_table_until_it_is_published(): void
    {
        $news = self::createBlogCategory('News', 'news', published: true);
        $recipes = self::createBlogCategory('Recipes', 'recipes', published: true);
        $post = self::createBlogPost('Ten ways to style a scarf', 'ten-ways-to-style-a-scarf', [$news], published: true);

        self::saveChanges($post, [['op' => 'set_entry_value', 'field' => 'categories', 'locale' => null, 'value' => [$recipes->getUuid()]]]);

        self::assertSame([['categories', $news->getUuid()]], self::referencesOf($post), 'The draft\'s categories live in the draft');

        self::publishEntry($post);

        self::assertSame([['categories', $recipes->getUuid()]], self::referencesOf($post));
    }

    /**
     * @test
     */
    public function it_empties_the_rows_of_a_post_published_without_categories_and_deletes_them_with_it(): void
    {
        $news = self::createBlogCategory('News', 'news', published: true);
        $post = self::createBlogPost('Ten ways to style a scarf', 'ten-ways-to-style-a-scarf', [$news], published: true);

        self::saveChanges($post, [['op' => 'set_entry_value', 'field' => 'categories', 'locale' => null, 'value' => []]]);
        self::publishEntry($post);

        self::assertSame([], self::referencesOf($post));

        self::saveChanges($post, [['op' => 'set_entry_value', 'field' => 'categories', 'locale' => null, 'value' => [$news->getUuid()]]]);
        self::publishEntry($post);
        $id = $post->getId();

        /** @var EntryDeleterInterface $deleter */
        $deleter = self::getContainer()->get(EntryDeleterInterface::class);
        $deleter->delete($post);

        // The database deletes the rows with the entry, so they're counted in SQL
        self::assertEquals(0, self::entityManager()->getConnection()->fetchOne('SELECT COUNT(*) FROM setono_sylius_cms__entry_reference WHERE entry_id = ?', [$id]));
    }

    /**
     * @test
     */
    public function it_dates_a_post_by_its_first_publish_and_keeps_that_date_when_it_is_published_again(): void
    {
        self::mockTime('2026-11-13 09:00:00 UTC');
        $post = self::createBlogPost('Ten ways to style a scarf', 'ten-ways-to-style-a-scarf', published: true);

        $post = self::reloaded($post);
        self::assertSame('2026-11-13T09:00:00+00:00', $post->getPublishedAt()?->format(\DATE_ATOM));

        self::mockTime('2026-11-20 12:00:00 UTC');
        self::saveChanges($post, [['op' => 'set_entry_value', 'field' => 'title', 'locale' => 'en_US', 'value' => 'Twelve ways to style a scarf']]);
        self::publishEntry($post);

        self::assertSame('2026-11-13T09:00:00+00:00', self::reloaded($post)->getPublishedAt()?->format(\DATE_ATOM));
    }

    /**
     * Editors can change the date, such as to backdate a post, before its first publish or after it
     *
     * @test
     */
    public function it_keeps_a_date_an_editor_backdated_a_post_to(): void
    {
        self::mockTime('2026-11-13 09:00:00 UTC');
        $post = self::createBlogPost('Ten ways to style a scarf', 'ten-ways-to-style-a-scarf');

        self::saveChanges($post, [['op' => 'set_entry_value', 'field' => 'publishedAt', 'locale' => null, 'value' => '2024-03-01T08:00:00Z']]);
        self::publishEntry($post);

        $post = self::reloaded($post);
        self::assertSame('2024-03-01T08:00:00+00:00', $post->getPublishedAt()?->format(\DATE_ATOM));

        self::saveChanges($post, [['op' => 'set_entry_value', 'field' => 'publishedAt', 'locale' => null, 'value' => '2023-12-24T18:00:00Z']]);
        self::publishEntry($post);

        self::assertSame('2023-12-24T18:00:00+00:00', self::reloaded($post)->getPublishedAt()?->format(\DATE_ATOM));
    }

    private static function reloaded(BlogPostInterface $post): BlogPostInterface
    {
        self::entityManager()->clear();
        $reloaded = self::entityManager()->find($post::class, $post->getId());
        self::assertInstanceOf(BlogPostInterface::class, $reloaded);

        return $reloaded;
    }

    /**
     * @param list<array{string, string}> $references
     *
     * @return list<array{string, string}>
     */
    private static function sorted(array $references): array
    {
        sort($references);

        return $references;
    }
}
