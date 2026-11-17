<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Setono\SyliusCMSPlugin\Tests\Support\Catalog;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\Html;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The Link list block and its Link children: simple navigation, such as footer links, as a <nav> with a list item per
 * link. A link that doesn't resolve renders no list item. See "Link list and Link" in docs/spec/blocks.md
 */
final class LinkListTest extends WebTestCase
{
    use Catalog;
    use Entries;

    private const LINK_LIST = '0193a5be-0000-7000-8000-0000000000a1';

    /**
     * @test
     */
    public function it_renders_a_link_list_as_a_nav_with_its_heading_and_a_list_item_per_link(): void
    {
        $client = self::createClient();
        self::createProduct('T_SHIRT_RED', 'Red T-shirt');
        self::createPageWithBlocks('Help', 'help', [self::linkList(['en_US' => 'Customer service'], [
            ['target' => 'url', 'ref' => ['en_US' => '/en_US/faq'], 'anchor' => 'returns', 'label' => ['en_US' => 'Returns']],
            ['target' => 'product', 'ref' => 'T_SHIRT_RED', 'anchor' => null, 'label' => []],
        ])], published: true);

        $crawler = $client->request('GET', '/en_US/help');

        self::assertResponseIsSuccessful();
        self::assertSame(
            '<nav class="cms-link-list" aria-label="Customer service">'
            . '<h2 class="cms-link-list__heading">Customer service</h2>'
            . '<ul class="cms-link-list__links">'
            . '<li class="cms-link"><a href="/en_US/faq#returns">Returns</a></li>'
            . '<li class="cms-link"><a href="http://localhost/en_US/products/red-t-shirt">Red T-shirt</a></li>'
            . '</ul>'
            . '</nav>',
            Html::withoutWhitespaceBetweenTags($crawler->filter('.cms-tree > .cms-link-list')->outerHtml()),
        );
    }

    /**
     * @test
     */
    public function it_renders_a_link_list_without_a_heading_when_its_heading_is_empty(): void
    {
        $client = self::createClient();
        self::createPageWithBlocks('Help', 'help', [self::linkList([], [
            ['target' => 'url', 'ref' => ['en_US' => 'mailto:help@example.com'], 'anchor' => null, 'label' => []],
        ])], published: true);

        $crawler = $client->request('GET', '/en_US/help');

        self::assertSame(
            '<nav class="cms-link-list"><ul class="cms-link-list__links"><li class="cms-link"><a href="mailto:help@example.com">help@example.com</a></li></ul></nav>',
            Html::withoutWhitespaceBetweenTags($crawler->filter('.cms-tree > .cms-link-list')->outerHtml()),
        );
    }

    /**
     * @test
     */
    public function it_leaves_out_the_list_item_of_a_link_to_a_taxon_that_was_deleted(): void
    {
        $client = self::createClient();
        $taxon = self::createTaxon('T_SHIRTS', 'T-shirts');
        self::createTaxon('MUGS', 'Mugs');
        self::createPageWithBlocks('Help', 'help', [self::linkList(['en_US' => 'Categories'], [
            ['target' => 'taxon', 'ref' => 'T_SHIRTS', 'anchor' => null, 'label' => []],
            ['target' => 'taxon', 'ref' => 'MUGS', 'anchor' => null, 'label' => []],
        ])], published: true);
        self::entityManager()->remove($taxon);
        self::entityManager()->flush();

        $crawler = $client->request('GET', '/en_US/help');

        self::assertResponseIsSuccessful();
        self::assertSame(
            '<ul class="cms-link-list__links"><li class="cms-link"><a href="http://localhost/en_US/taxons/mugs">Mugs</a></li></ul>',
            Html::withoutWhitespaceBetweenTags($crawler->filter('.cms-tree > .cms-link-list > .cms-link-list__links')->outerHtml()),
        );
    }

    /**
     * @param array<string, string> $heading
     * @param list<array<string, mixed>> $links
     */
    private static function linkList(array $heading, array $links): StoredBlock
    {
        $children = [];
        foreach ($links as $index => $link) {
            $children[] = new StoredBlock(sprintf('0193a5be-0000-7000-8000-0000000000c%d', $index), 'link', 1, fields: ['link' => $link]);
        }

        return new StoredBlock(self::LINK_LIST, 'link_list', 1, fields: [] === $heading ? [] : ['heading' => $heading], children: $children);
    }
}
