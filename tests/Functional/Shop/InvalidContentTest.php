<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Published content that's no longer valid, such as after a code change, still renders what it can.
 * See "Rendering a block tree" in docs/spec/rendering.md and "Fields" in docs/spec/content-model.md
 */
final class InvalidContentTest extends WebTestCase
{
    use Entries;

    /**
     * @test
     */
    public function a_tree_with_a_block_of_a_type_the_code_no_longer_has_renders_everything_else(): void
    {
        $client = self::createClient();
        self::createPageWithBlocks('About us', 'about-us', [
            self::heading('0193a5be-0000-7000-8000-000000000001', 'Before'),
            new StoredBlock('0193a5be-0000-7000-8000-000000000002', 'banner', 1, fields: ['title' => 'Winter sale'], children: [
                self::heading('0193a5be-0000-7000-8000-000000000003', 'Inside the banner'),
            ]),
            self::heading('0193a5be-0000-7000-8000-000000000004', 'After'),
        ], published: true);

        $crawler = $client->request('GET', '/en_US/about-us');

        self::assertResponseIsSuccessful();
        self::assertSame(['Before', 'After'], $crawler->filter('.cms-tree .cms-heading')->each(static fn ($heading): string => $heading->text()));
        self::assertStringNotContainsString('Winter sale', (string) $client->getResponse()->getContent());
    }

    /**
     * @test
     */
    public function a_stored_value_that_does_not_fit_its_field_type_counts_as_missing(): void
    {
        $client = self::createClient();
        $heading = self::heading('0193a5be-0000-7000-8000-000000000001', 'Hello');
        // A level the Choice doesn't offer, so the Heading keeps its default level, h2
        $heading->setValue('level', null, 'h6');
        self::createPageWithBlocks('About us', 'about-us', [$heading], published: true);

        $crawler = $client->request('GET', '/en_US/about-us');

        self::assertResponseIsSuccessful();
        self::assertSame('Hello', $crawler->filter('.cms-tree h2.cms-heading')->text());
        self::assertCount(0, $crawler->filter('h6'));
    }
}
