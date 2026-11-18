<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional;

use Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api\BuilderApiTestCase;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;

/**
 * The Embed code block with setono_sylius_cms.embed_code on, as the test application has it: the code renders unescaped
 * in the shop, and as a placeholder in the builder's preview, so its scripts never run there.
 * See "Embed code" in docs/spec/blocks.md and ADR 0018
 */
final class EmbedCodeTest extends BuilderApiTestCase
{
    private const KEY = '0193a5be-0000-7000-8000-0000000000e1';

    private const CODE = '<div id="reviews" data-stars="5"></div><script>document.documentElement.dataset.embedMarker = "set";</script>';

    /**
     * @test
     */
    public function it_offers_the_block_in_the_registry_with_its_code_field(): void
    {
        $client = self::signedIn();

        $registry = self::json($client, 'GET', '/admin/cms/api/registry');

        self::assertIsArray($registry);
        self::assertIsArray($registry['blockTypes']);
        $embedCode = array_column($registry['blockTypes'], null, 'code')['embed_code'] ?? null;
        self::assertIsArray($embedCode);
        self::assertSame('Embed code', $embedCode['label']);
        self::assertFalse($embedCode['disabled']);
        self::assertSame([[
            'name' => 'code',
            'schema' => [
                'type' => 'string',
                'x-cms-type' => 'code',
                'x-cms-label' => 'Code',
                'x-cms-help' => 'HTML, scripts included, such as a review widget or a newsletter form. It runs in the shop as it is, but not in the builder\'s preview.',
                'x-cms-required' => false,
                'x-cms-translatable' => false,
            ],
        ]], $embedCode['fields']);
    }

    /**
     * @test
     */
    public function it_renders_the_code_unescaped_in_the_shop(): void
    {
        $client = self::createClient();
        self::createPageWithBlocks('Reviews', 'reviews', [self::embedCode(self::CODE)], published: true);

        $client->request('GET', '/en_US/reviews');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('<div class="cms-embed-code">' . self::CODE . '</div>', (string) $client->getResponse()->getContent());
        self::assertSelectorExists('.cms-tree > .cms-embed-code > #reviews[data-stars="5"]');
        self::assertSelectorNotExists('.cms-embed-code--placeholder');
    }

    /**
     * @test
     */
    public function it_renders_a_placeholder_in_its_place_in_the_builders_preview(): void
    {
        $client = self::createClient();
        $page = self::createPageWithBlocks('Reviews', 'reviews', [self::embedCode(self::CODE)]);

        $client->request('GET', '/en_US/reviews?_cms_preview=' . self::previewToken($page));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.cms-tree > .cms-embed-code.cms-embed-code--placeholder');
        self::assertSelectorTextSame('.cms-embed-code--placeholder', 'Embed code runs in the shop, not in the builder\'s preview.');
        self::assertStringNotContainsString('embedMarker', (string) $client->getResponse()->getContent());
        self::assertSelectorNotExists('#reviews');
    }

    /**
     * @test
     */
    public function it_renders_a_placeholder_in_the_blocks_the_preview_patches_in(): void
    {
        $client = self::createClient();
        $page = self::createPageWithBlocks('Reviews', 'reviews', []);
        $csrfToken = self::openBuilder($client, $page);

        $response = self::json($client, 'POST', '/admin/cms/api/preview/render?_cms_preview=' . self::previewToken($page), ['blocks' => [
            ['key' => self::KEY, 'type' => 'embed_code', 'version' => 1, 'style' => null, 'fields' => ['code' => self::CODE], 'translated' => (object) [], 'conditions' => [], 'children' => []],
        ]], $csrfToken);

        self::assertResponseIsSuccessful();
        self::assertIsArray($response);
        self::assertIsArray($response['blocks'] ?? null);
        $html = $response['blocks'][self::KEY] ?? null;
        self::assertIsString($html);
        self::assertStringContainsString('<div class="cms-embed-code cms-embed-code--placeholder">', $html);
        self::assertStringNotContainsString('embedMarker', $html);
    }

    /**
     * @test
     */
    public function it_publishes_a_new_embed_code_block(): void
    {
        $client = self::createClient();
        $page = self::createPage('Reviews', 'reviews', published: true);
        $csrfToken = self::openBuilder($client, $page);
        self::saveChanges($page, [['op' => 'add_block', 'parent' => null, 'index' => 0, 'block' => ['key' => self::KEY, 'type' => 'embed_code', 'version' => 1, 'fields' => ['code' => self::CODE]]]]);

        self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/publish', $page->getUuid()), null, $csrfToken);

        self::assertResponseIsSuccessful();
        $client->request('GET', '/en_US/reviews');
        self::assertSelectorExists('.cms-tree > .cms-embed-code > #reviews');
    }

    private static function embedCode(string $code): StoredBlock
    {
        return new StoredBlock(self::KEY, 'embed_code', 1, fields: ['code' => $code]);
    }
}
