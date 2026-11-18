<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Blocks;
use Setono\SyliusCMSPlugin\Tests\Support\Catalog;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\Html;
use Setono\SyliusCMSPlugin\Tests\Support\Placements;
use Setono\SyliusCMSPlugin\Tests\Support\ProfiledQueries;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Snippets that block trees reference through Snippet blocks. See "Snippet" in docs/spec/blocks.md and "Rendering a
 * block tree" in docs/spec/rendering.md
 */
final class SnippetBlockTest extends WebTestCase
{
    use Catalog;
    use Entries;
    use Placements;
    use ProfiledQueries;

    private const ABOUT = '0193a5be-0000-7000-8000-000000000001';

    private const FOOTER = '0193a5be-0000-7000-8000-000000000002';

    private const REFERENCE = '0193a5be-0000-7000-8000-000000000003';

    private const A = '0193a5be-0000-7000-8000-00000000000a';

    private const B = '0193a5be-0000-7000-8000-00000000000b';

    private const A_SHOWS_B = '0193a5be-0000-7000-8000-0000000000ab';

    private const B_SHOWS_A = '0193a5be-0000-7000-8000-0000000000ba';

    private const LOGO = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d01';

    private const BADGE = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d02';

    /**
     * @test
     */
    public function it_renders_the_published_content_of_the_snippet_a_page_references_with_no_markup_of_its_own(): void
    {
        $client = self::createClient();
        $footer = self::createSnippet('Footer', [self::heading(self::FOOTER, 'Free shipping over $50')], published: true);
        // The draft isn't what shoppers see
        self::saveChanges($footer, [['op' => 'set_value', 'key' => self::FOOTER, 'field' => 'text', 'locale' => 'en_US', 'value' => 'Free shipping over $40']]);
        self::createPageWithBlocks('About us', 'about-us', [self::heading(self::ABOUT, 'About us'), Blocks::snippet(self::REFERENCE, $footer)], published: true);

        $crawler = $client->request('GET', '/en_US/about-us');

        self::assertResponseIsSuccessful();
        self::assertSame(
            '<div class="cms-tree"><h2 class="cms-heading">About us</h2><h2 class="cms-heading">Free shipping over $50</h2></div>',
            Html::withoutWhitespaceBetweenTags($crawler->filter('.cms-tree')->outerHtml()),
        );
    }

    /**
     * @test
     */
    public function it_renders_nothing_for_a_snippet_that_is_not_published(): void
    {
        $client = self::createClient();
        $footer = self::createSnippet('Footer', [self::heading(self::FOOTER, 'Free shipping over $50')]);
        self::createPageWithBlocks('About us', 'about-us', [self::heading(self::ABOUT, 'About us'), Blocks::snippet(self::REFERENCE, $footer)], published: true);

        $crawler = $client->request('GET', '/en_US/about-us');

        self::assertResponseIsSuccessful();
        self::assertSame(['About us'], $crawler->filter('.cms-tree h2')->each(static fn (Crawler $node): string => $node->text()));
    }

    /**
     * The snippet a Snippet block shows gets the host-page context of the tree it sits in: here, the product of the
     * product page whose slot shows the snippet that holds the Snippet block
     *
     * @test
     */
    public function it_passes_the_context_of_the_tree_it_sits_in_on_to_the_snippet_it_shows(): void
    {
        $client = self::createClient();
        self::createProduct('T_SHIRT', 'T-shirt');
        $note = new StoredBlock(self::FOOTER, 'app_product_note', 1);
        $note->setValue('text', 'en_US', 'Size guide for');
        $sizeGuide = self::createSnippet('Size guide', [$note], published: true, context: ['product']);
        self::place(self::createSnippet('Product sidebar', [Blocks::snippet(self::REFERENCE, $sizeGuide)], published: true, context: ['product']), 'product.after_price');

        $crawler = $client->request('GET', '/en_US/products/t-shirt');

        self::assertResponseIsSuccessful();
        self::assertSame(['Size guide for T-shirt'], $crawler->filter('.cms-slot--product-after-price .app-product-note')->each(static fn (Crawler $node): string => $node->text()));
    }

    /**
     * @test
     */
    public function it_skips_a_snippet_the_second_time_it_would_render_inside_itself(): void
    {
        $client = self::createClient();
        [$a] = self::aInsideBInsideA();
        self::createPageWithBlocks('About us', 'about-us', [Blocks::snippet(self::REFERENCE, $a)], published: true);
        self::place($a, 'shop.footer');

        $crawler = $client->request('GET', '/en_US/about-us');

        self::assertResponseIsSuccessful();
        self::assertSame(['A', 'B'], $crawler->filter('.cms-tree h2')->each(static fn (Crawler $node): string => $node->text()));
        self::assertSame(['A', 'B'], $crawler->filter('.cms-slot--shop-footer h2')->each(static fn (Crawler $node): string => $node->text()));
    }

    /**
     * @test
     */
    public function it_loads_the_references_of_a_referenced_snippet_s_tree_with_the_page_s_in_one_pass(): void
    {
        $client = self::createClient();
        $footer = self::createSnippet('Footer', [self::image(self::FOOTER, self::BADGE)], published: true);
        self::createPageWithBlocks('About us', 'about-us', [self::image(self::ABOUT, self::LOGO), Blocks::snippet(self::REFERENCE, $footer)], published: true);
        $client->request('GET', '/en_US/about-us');

        $client->enableProfiler();
        $client->request('GET', '/en_US/about-us');

        self::assertResponseIsSuccessful();
        $mediaQueries = self::queriesOf($client, 'setono_sylius_cms__media_item');
        self::assertCount(1, $mediaQueries, 'The page\'s media items and the snippet\'s load together');
    }

    /**
     * Snippet A shows B, and B shows A
     *
     * @return array{SnippetInterface, SnippetInterface}
     */
    private static function aInsideBInsideA(): array
    {
        $a = self::createSnippet('A', [self::heading(self::A, 'A')], published: true);
        $b = self::createSnippet('B', [self::heading(self::B, 'B'), Blocks::snippet(self::B_SHOWS_A, $a)], published: true);
        $a->setBlockTree(new StoredTree(null, [self::heading(self::A, 'A'), Blocks::snippet(self::A_SHOWS_B, $b)]));
        self::entityManager()->flush();

        return [$a, $b];
    }

    private static function image(string $key, string $mediaItem): StoredBlock
    {
        return new StoredBlock($key, 'image', 1, fields: ['image' => $mediaItem]);
    }
}
