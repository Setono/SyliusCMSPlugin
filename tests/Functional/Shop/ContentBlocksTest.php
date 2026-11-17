<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\Html;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Rich text, and the Accordion with its items. See "Rich text" and "Accordion and Accordion item" in docs/spec/blocks.md
 */
final class ContentBlocksTest extends WebTestCase
{
    use Entries;

    /**
     * @test
     */
    public function it_renders_the_html_of_a_published_rich_text_block_in_cms_rich_text(): void
    {
        $client = self::createClient();
        self::createPageWithBlocks('Story', 'story', [self::richText('0193a5be-0000-7000-8000-000000000001', 'A ', 'bold', ' word')], published: true);

        $crawler = $client->request('GET', '/en_US/story');

        self::assertResponseIsSuccessful();
        self::assertSame(
            '<div class="cms-rich-text"><p>A <strong>bold</strong> word</p></div>',
            $crawler->filter('.cms-tree > .cms-rich-text')->outerHtml(),
        );
    }

    /**
     * @test
     */
    public function it_renders_each_accordion_item_as_details_with_its_title_in_the_summary(): void
    {
        $client = self::createClient();
        $item = new StoredBlock('0193a5be-0000-7000-8000-000000000002', 'accordion_item', 1, fields: [
            'title' => ['en_US' => 'Shipping'],
            'content' => ['en_US' => self::document('We ship ', 'fast', '.')],
        ]);
        $untitled = new StoredBlock('0193a5be-0000-7000-8000-000000000003', 'accordion_item', 1);
        self::createPageWithBlocks('FAQ', 'faq', [
            new StoredBlock('0193a5be-0000-7000-8000-000000000001', 'accordion', 1, children: [$item, $untitled]),
        ], published: true);

        $crawler = $client->request('GET', '/en_US/faq');

        self::assertResponseIsSuccessful();
        self::assertSame(
            '<div class="cms-accordion">'
            . '<details class="cms-accordion-item"><summary class="cms-accordion-item__title">Shipping</summary>'
            . '<div class="cms-accordion-item__content"><p>We ship <strong>fast</strong>.</p></div></details>'
            . '<details class="cms-accordion-item"><summary class="cms-accordion-item__title"></summary>'
            . '<div class="cms-accordion-item__content"></div></details>'
            . '</div>',
            Html::withoutWhitespaceBetweenTags($crawler->filter('.cms-tree > .cms-accordion')->outerHtml()),
        );
    }

    /**
     * A Rich text block with one paragraph in en_US, whose middle part is bold
     */
    private static function richText(string $key, string $before, string $bold, string $after): StoredBlock
    {
        return new StoredBlock($key, 'rich_text', 1, fields: ['content' => ['en_US' => self::document($before, $bold, $after)]]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function document(string $before, string $bold, string $after): array
    {
        return ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => $before],
            ['type' => 'text', 'text' => $bold, 'marks' => [['type' => 'bold']]],
            ['type' => 'text', 'text' => $after],
        ]]]];
    }
}
