<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class StructureBlocksTest extends WebTestCase
{
    use Entries;

    /**
     * @test
     */
    public function a_published_page_renders_a_group_of_columns_with_a_heading_in_a_column(): void
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
            self::withoutWhitespaceBetweenTags($crawler->filter('.cms-tree > .cms-group')->outerHtml()),
        );
    }

    /**
     * @test
     */
    public function a_full_width_group_is_marked_full_width(): void
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
    public function a_spacer_renders_its_size_and_is_medium_by_default(): void
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

    private static function withoutWhitespaceBetweenTags(string $html): string
    {
        return (string) preg_replace('/>\s+</', '><', trim($html));
    }
}
