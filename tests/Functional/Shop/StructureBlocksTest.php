<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Setono\SyliusCMSPlugin\Tests\Support\Html;

final class StructureBlocksTest extends WebTestCase
{
    use Entries;

    /**
     * @test
     */
    public function it_renders_a_published_page_with_a_group_of_columns_with_a_heading_in_a_column(): void
    {
        $client = self::createClient();
        $group = new StoredBlock('0193a5be-0000-7000-8000-000000000001', 'group', 1, children: [
            new StoredBlock('0193a5be-0000-7000-8000-000000000002', 'columns', 1, children: [
                new StoredBlock('0193a5be-0000-7000-8000-000000000003', 'column', 1, children: [
                    self::heading('0193a5be-0000-7000-8000-000000000004', 'Left'),
                ]),
                new StoredBlock('0193a5be-0000-7000-8000-000000000005', 'column', 1, children: [
                    self::heading('0193a5be-0000-7000-8000-000000000006', 'Right'),
                ]),
            ]),
        ]);
        self::createPageWithBlocks('Layout', 'layout', [$group], published: true);

        $crawler = $client->request('GET', '/en_US/layout');

        self::assertResponseIsSuccessful();
        self::assertSame(
            '<div class="cms-group"><div class="cms-group__inner"><div class="cms-columns">'
            . '<div class="cms-column"><h2 class="cms-heading">Left</h2></div>'
            . '<div class="cms-column"><h2 class="cms-heading">Right</h2></div>'
            . '</div></div></div>',
            Html::withoutWhitespaceBetweenTags($crawler->filter('.cms-tree > .cms-group')->outerHtml()),
        );
    }

    /**
     * @test
     */
    public function it_marks_a_full_width_group_full_width(): void
    {
        $client = self::createClient();
        $group = new StoredBlock('0193a5be-0000-7000-8000-000000000001', 'group', 1, fields: ['fullWidth' => true]);
        self::createPageWithBlocks('Layout', 'layout', [$group], published: true);

        $client->request('GET', '/en_US/layout');

        self::assertSelectorExists('.cms-tree > .cms-group.cms-full-width > .cms-group__inner');
    }

    /**
     * @test
     */
    public function it_renders_a_spacers_size_which_is_medium_by_default(): void
    {
        $client = self::createClient();
        self::createPageWithBlocks('Layout', 'layout', [
            new StoredBlock('0193a5be-0000-7000-8000-000000000001', 'spacer', 1, fields: ['size' => 'large']),
            new StoredBlock('0193a5be-0000-7000-8000-000000000002', 'spacer', 1),
        ], published: true);

        $crawler = $client->request('GET', '/en_US/layout');

        $spacers = $crawler->filter('.cms-tree > .cms-spacer');
        self::assertSame('<div class="cms-spacer cms-spacer--large" aria-hidden="true"></div>', $spacers->eq(0)->outerHtml());
        self::assertSame('<div class="cms-spacer cms-spacer--medium" aria-hidden="true"></div>', $spacers->eq(1)->outerHtml());
    }

    /**
     * @test
     */
    public function it_renders_the_dark_style_class_of_a_published_group_next_to_its_own(): void
    {
        $client = self::createClient();
        self::createPageWithBlocks('Dark', 'dark', [
            new StoredBlock('0193a5be-0000-7000-8000-000000000001', 'group', 1, style: 'dark', children: [self::heading('0193a5be-0000-7000-8000-000000000002', 'Night')]),
        ], published: true);

        $crawler = $client->request('GET', '/en_US/dark');

        self::assertResponseIsSuccessful();
        self::assertSame('cms-group cms-group--dark', $crawler->filter('.cms-tree > .cms-group')->attr('class'));
    }

    /**
     * @test
     */
    public function it_renders_the_class_of_a_group_style_the_app_adds_in_its_config(): void
    {
        $client = self::createClient();
        self::createPageWithBlocks('Branded', 'branded', [
            new StoredBlock('0193a5be-0000-7000-8000-000000000001', 'group', 1, style: 'brand', fields: ['fullWidth' => true]),
        ], published: true);

        $crawler = $client->request('GET', '/en_US/branded');

        self::assertSame('cms-group cms-full-width app-group--brand', $crawler->filter('.cms-tree > .cms-group')->attr('class'));
    }

    /**
     * @test
     */
    public function it_renders_a_group_with_no_style_plain(): void
    {
        $client = self::createClient();
        self::createPageWithBlocks('Plain', 'plain', [new StoredBlock('0193a5be-0000-7000-8000-000000000001', 'group', 1)], published: true);

        $crawler = $client->request('GET', '/en_US/plain');

        self::assertSame('cms-group', $crawler->filter('.cms-tree > .cms-group')->attr('class'));
    }
}
