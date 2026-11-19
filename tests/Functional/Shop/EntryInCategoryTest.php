<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Setono\SyliusCMSPlugin\Model\BlogCategoryInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Blog;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The Entry in category condition kind hides blocks on an entry that isn't in any of its categories. See "Condition
 * kinds" in docs/spec/targeting.md
 */
final class EntryInCategoryTest extends WebTestCase
{
    use Blog;
    use Entries;

    /**
     * @test
     */
    public function it_shows_a_block_on_the_posts_in_any_of_its_categories(): void
    {
        $client = self::createClient();
        $news = self::createBlogCategory('News', 'news', published: true);
        $events = self::createBlogCategory('Events', 'events', published: true);
        $recipes = self::createBlogCategory('Recipes', 'recipes', published: true);

        self::createBlogPost('Opening night', 'opening-night', [$recipes, $events], published: true, blocks: self::blocks($news, $events));
        self::createBlogPost('Soup', 'soup', [$recipes], published: true, blocks: self::blocks($news, $events));

        self::assertSame(['Everywhere', 'News or events'], self::headings($client->request('GET', '/en_US/blog/opening-night')));
        self::assertSame(['Everywhere', 'Not news or events'], self::headings($client->request('GET', '/en_US/blog/soup')), 'Negated, it shows the block on the others');
    }

    /**
     * An entry whose content type has no categories is in none
     *
     * @test
     */
    public function it_hides_the_block_on_an_entry_without_categories(): void
    {
        $client = self::createClient();
        $news = self::createBlogCategory('News', 'news', published: true);
        self::createPageWithBlocks('About us', 'about-us', self::blocks($news), published: true);

        self::assertSame(['Everywhere', 'Not news or events'], self::headings($client->request('GET', '/en_US/about-us')));
    }

    /**
     * The post's published categories decide, and a preview of its working copy shows its drafted ones
     *
     * @test
     */
    public function it_reads_the_categories_of_the_post_as_it_is_shown(): void
    {
        $client = self::createClient();
        $news = self::createBlogCategory('News', 'news', published: true);
        $recipes = self::createBlogCategory('Recipes', 'recipes', published: true);
        $post = self::createBlogPost('Soup', 'soup', [$recipes], published: true, blocks: self::blocks($news));

        self::saveChanges($post, [['op' => 'set_entry_value', 'field' => 'categories', 'locale' => null, 'value' => [$news->getUuid()]]]);

        self::assertSame(['Everywhere', 'Not news or events'], self::headings($client->request('GET', '/en_US/blog/soup')));
        self::assertSame(['Everywhere', 'News or events'], self::headings($client->request('GET', '/en_US/blog/soup?_cms_preview=' . self::previewToken($post))));
    }

    /**
     * @return list<StoredBlock>
     */
    private static function blocks(BlogCategoryInterface ...$categories): array
    {
        $uuids = array_values(array_map(static fn (BlogCategoryInterface $category): string => $category->getUuid(), $categories));

        $inCategory = self::heading('0193a5be-0000-7000-8000-000000000002', 'News or events');
        $inCategory->conditions = [['kind' => 'entry_in_category', 'negate' => false, 'params' => ['categories' => $uuids]]];

        $notInCategory = self::heading('0193a5be-0000-7000-8000-000000000003', 'Not news or events');
        $notInCategory->conditions = [['kind' => 'entry_in_category', 'negate' => true, 'params' => ['categories' => $uuids]]];

        return [self::heading('0193a5be-0000-7000-8000-000000000001', 'Everywhere'), $inCategory, $notInCategory];
    }

    /**
     * @return list<string>
     */
    private static function headings(Crawler $crawler): array
    {
        self::assertResponseIsSuccessful();

        $headings = [];
        foreach ($crawler->filter('.cms-tree .cms-heading') as $heading) {
            $headings[] = $heading->textContent;
        }

        return $headings;
    }
}
