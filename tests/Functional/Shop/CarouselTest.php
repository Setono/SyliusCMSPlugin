<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Setono\SyliusCMSPlugin\Tests\Support\Catalog;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The Carousel block, whose slides are its visible children, and the Products block's carousel display. The markup
 * works without JS, and the carousel script reads the slides per view and the autoplay interval from it. See
 * "Carousel" and "Products" in docs/spec/blocks.md
 */
final class CarouselTest extends WebTestCase
{
    use Catalog;
    use Entries;

    private const CAROUSEL = '0193a5be-0000-7000-8000-0000000000a1';

    /**
     * @test
     */
    public function it_shows_each_visible_child_of_a_carousel_as_a_slide_and_leaves_out_a_hidden_one(): void
    {
        $client = self::createClient();
        $hidden = self::heading('0193a5be-0000-7000-8000-0000000000a3', 'Not in the web store');
        $hidden->conditions = [['kind' => 'channel', 'negate' => true, 'params' => ['channels' => ['WEB']]]];
        self::createPageWithBlocks('Campaign', 'campaign', [self::carousel([], [
            self::heading('0193a5be-0000-7000-8000-0000000000a2', 'Summer sale'),
            $hidden,
            self::heading('0193a5be-0000-7000-8000-0000000000a4', 'Free shipping'),
        ])], published: true);

        $crawler = $client->request('GET', '/en_US/campaign');

        self::assertResponseIsSuccessful();
        $slides = $crawler->filter('.cms-tree > .cms-carousel > .cms-carousel__track > .cms-carousel__slide');
        self::assertSame(['Summer sale', 'Free shipping'], $slides->each(static fn (Crawler $slide): string => $slide->filter('.cms-heading')->text()));
        self::assertCount(2, $crawler->filter('.cms-carousel__track')->children(), 'The track holds only the slides');
    }

    /**
     * @test
     */
    public function it_prints_the_slides_per_view_and_leaves_out_autoplay_when_it_is_off(): void
    {
        $client = self::createClient();
        self::createPageWithBlocks('Campaign', 'campaign', [self::carousel([], [self::heading('0193a5be-0000-7000-8000-0000000000a2', 'Summer sale')])], published: true);

        $crawler = $client->request('GET', '/en_US/campaign');

        self::assertResponseIsSuccessful();
        $carousel = $crawler->filter('.cms-tree > .cms-carousel');
        self::assertSame('cms-carousel', $carousel->attr('class'));
        self::assertSame('1', $carousel->attr('data-cms-per-view'));
        self::assertNull($carousel->attr('data-cms-autoplay'));
    }

    /**
     * @test
     */
    public function it_prints_the_editor_s_slides_per_view_and_the_autoplay_interval_in_milliseconds_and_no_inline_styles(): void
    {
        $client = self::createClient();
        self::createPageWithBlocks('Campaign', 'campaign', [self::carousel(['perView' => '3', 'autoplay' => true, 'interval' => 8], [
            self::heading('0193a5be-0000-7000-8000-0000000000a2', 'Summer sale'),
        ])], published: true);

        $crawler = $client->request('GET', '/en_US/campaign');

        self::assertResponseIsSuccessful();
        $carousel = $crawler->filter('.cms-tree > .cms-carousel');
        self::assertSame('3', $carousel->attr('data-cms-per-view'));
        self::assertSame('8000', $carousel->attr('data-cms-autoplay'));
        self::assertCount(0, $carousel->filter('[style]'), 'Theme CSS always wins over no inline style');
        self::assertNull($carousel->attr('style'));
    }

    /**
     * @test
     */
    public function it_autoplays_every_five_seconds_by_default(): void
    {
        $client = self::createClient();
        self::createPageWithBlocks('Campaign', 'campaign', [self::carousel(['autoplay' => true], [
            self::heading('0193a5be-0000-7000-8000-0000000000a2', 'Summer sale'),
        ])], published: true);

        $crawler = $client->request('GET', '/en_US/campaign');

        self::assertResponseIsSuccessful();
        self::assertSame('5000', $crawler->filter('.cms-tree > .cms-carousel')->attr('data-cms-autoplay'));
    }

    /**
     * @test
     */
    public function it_shows_a_products_block_s_product_cards_as_a_carousel_of_four_per_view(): void
    {
        $client = self::createClient();
        self::createProduct('MUG', 'Mug');
        self::createProduct('T_SHIRT', 'T-shirt');
        self::createPageWithBlocks('Shop now', 'shop-now', [
            new StoredBlock('0193a5be-0000-7000-8000-0000000000c1', 'products', 1, fields: ['products' => ['T_SHIRT', 'MUG'], 'display' => 'carousel']),
        ], published: true);

        $crawler = $client->request('GET', '/en_US/shop-now');

        self::assertResponseIsSuccessful();
        $products = $crawler->filter('.cms-tree > .cms-products');
        self::assertSame('cms-products cms-products--carousel', $products->attr('class'));
        $carousel = $products->filter('.cms-products > .cms-carousel');
        self::assertSame('4', $carousel->attr('data-cms-per-view'));
        self::assertNull($carousel->attr('data-cms-autoplay'));
        $items = $carousel->filter('.cms-carousel > .cms-carousel__track > .cms-carousel__slide > .cms-products__item');
        self::assertSame(['T-shirt', 'Mug'], $items->filter('.sylius-product-name')->each(static fn (Crawler $name): string => $name->text()));
    }

    /**
     * @param array<string, mixed> $fields
     * @param list<StoredBlock> $children
     */
    private static function carousel(array $fields, array $children): StoredBlock
    {
        return new StoredBlock(self::CAROUSEL, 'carousel', 1, fields: $fields, children: $children);
    }
}
