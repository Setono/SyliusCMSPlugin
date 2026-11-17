<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Setono\SyliusCMSPlugin\Tests\Support\Catalog;
use Setono\SyliusCMSPlugin\Tests\Support\Html;
use Setono\SyliusCMSPlugin\Tests\Support\Placements;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * The builder previews a snippet on the real shop page that hosts one of its placements, with a sample object. See
 * "Snippet preview" in docs/spec/builder.md and "Previewing a snippet" in docs/spec/slots.md
 */
final class SnippetPreviewTest extends BuilderApiTestCase
{
    use Catalog;
    use Placements;

    private const KEY = '0193a5be-0000-7000-8000-000000000001';

    /**
     * @test
     */
    public function it_previews_a_placement_on_the_product_page_of_a_sample_product_in_its_scope_with_the_draft_in_the_slot_outlined(): void
    {
        $client = self::createClient(server: ['HTTP_HOST' => 'admin.example']);
        self::createProduct('T_SHIRT', 'T-shirt');
        self::createProduct('MUG', 'Mug');
        $snippet = self::createSnippet('Mug care', [self::heading(self::KEY, 'Rinse after use')], context: ['product']);
        $placement = self::place($snippet, 'product.after_price');
        self::scope($placement, [['kind' => 'product', 'negate' => false, 'params' => ['products' => ['MUG']]]]);
        $csrfToken = self::openBuilder($client, $snippet);

        $url = self::previewUrl($client, $csrfToken, ['entry' => $snippet->getUuid(), 'placement' => $placement->getUuid()]);

        self::assertMatchesRegularExpression('#^http://admin\.example/en_US/products/mug\?_cms_preview=[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$#', $url, 'The sample product starts as one in the placement\'s scope');

        $crawler = $client->request('GET', $url);

        self::assertResponseStatusCodeSame(200);
        $outline = $crawler->filter('[data-cms-slot-preview="product.after_price"]');
        self::assertCount(1, $outline, 'The slot is outlined');
        self::assertSame('Product page: under the price', trim($outline->filter('[data-cms-slot-label]')->text()), 'and labelled');
        self::assertSame(
            sprintf('<!--cms:block %s heading--><h2 class="cms-heading"><!--cms:field %1$s text text-->Rinse after use<!--/cms:field %1$s text--></h2><!--/cms:block %1$s-->', self::KEY),
            Html::withoutWhitespaceBetweenTags($outline->filter('.cms-slot.cms-slot--product-after-price')->html()),
            'The draft shows in the slot, marked for the builder, though the snippet was never published',
        );
    }

    /**
     * Its bindings show the sample object's real values. See "Previewing a snippet" in docs/spec/slots.md
     *
     * @test
     */
    public function it_previews_a_snippet_without_placements_on_a_plain_page_with_a_sample_object_of_its_declared_context(): void
    {
        $client = self::createClient(server: ['HTTP_HOST' => 'admin.example']);
        self::createProduct('T_SHIRT', 'T-shirt');
        self::createProduct('MUG', 'Mug');
        $snippet = self::createSnippet('Product intro', [self::productName()], context: ['product']);
        $csrfToken = self::openBuilder($client, $snippet);

        $url = self::previewUrl($client, $csrfToken, ['entry' => $snippet->getUuid()]);

        self::assertMatchesRegularExpression('#^http://admin\.example/en_US/_cms/snippet-preview\?_cms_preview=[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$#', $url);
        $client->request('GET', $url);
        self::assertSelectorTextSame('.cms-slot .cms-heading', 'Mug', 'It starts with the first product by name');

        $client->request('GET', self::previewUrl($client, $csrfToken, ['entry' => $snippet->getUuid(), 'sample' => 'T_SHIRT']));
        self::assertSelectorTextSame('.cms-slot .cms-heading', 'T-shirt');
    }

    /**
     * Such as one disabled since the editor picked it
     *
     * @test
     */
    public function it_starts_again_with_a_sample_object_when_shoppers_can_t_see_the_one_picked(): void
    {
        $client = self::createClient();
        self::createProduct('T_SHIRT', 'T-shirt', enabled: false);
        self::createProduct('MUG', 'Mug');
        $snippet = self::createSnippet('Product intro', [self::productName()], context: ['product']);
        $csrfToken = self::openBuilder($client, $snippet);

        $client->request('GET', self::previewUrl($client, $csrfToken, ['entry' => $snippet->getUuid(), 'sample' => 'T_SHIRT']));

        self::assertSelectorTextSame('.cms-slot .cms-heading', 'Mug');
    }

    /**
     * The builder patches its edits in with the sample object too. See "Updating the preview" in docs/spec/builder.md
     *
     * @test
     */
    public function it_renders_the_blocks_the_builder_patches_in_with_the_sample_object(): void
    {
        $client = self::createClient();
        self::createProduct('MUG', 'Mug');
        $snippet = self::createSnippet('Product intro', context: ['product']);
        $placement = self::place($snippet, 'product.after_price');
        $csrfToken = self::openBuilder($client, $snippet);
        $url = self::previewUrl($client, $csrfToken, ['entry' => $snippet->getUuid(), 'placement' => $placement->getUuid()]);
        $token = self::queryOf($url)['_cms_preview'] ?? '';

        $response = self::json($client, 'POST', '/admin/cms/api/preview/render?_cms_preview=' . $token, ['blocks' => [[
            'key' => self::KEY, 'type' => 'heading', 'version' => 1,
            'fields' => ['text' => ['$bind' => 'product.name', '$params' => [], '$fallback' => ['en_US' => 'This product']]],
        ]]], $csrfToken);

        self::assertResponseIsSuccessful();
        self::assertIsArray($response);
        self::assertSame(sprintf('<!--cms:block %s heading--><h2 class="cms-heading">Mug</h2><!--/cms:block %1$s-->', self::KEY), Html::withoutWhitespaceBetweenTags((string) ($response['blocks'][self::KEY] ?? '')));
    }

    /**
     * A heading bound to the product's name, which shows "This product" without one
     */
    private static function productName(): StoredBlock
    {
        return new StoredBlock(self::KEY, 'heading', 1, fields: ['text' => ['$bind' => 'product.name', '$params' => [], '$fallback' => ['en_US' => 'This product']]]);
    }

    /**
     * @return array<string, string>
     */
    private static function queryOf(string $url): array
    {
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);

        /** @var array<string, string> $query */
        return $query;
    }

    /**
     * @param array<string, mixed> $body
     */
    private static function previewUrl(KernelBrowser $client, string $csrfToken, array $body): string
    {
        $response = self::json($client, 'POST', '/admin/cms/api/preview/token', $body + ['channel' => 'WEB', 'locale' => 'en_US', 'shownTo' => '@guest'], $csrfToken);
        self::assertResponseIsSuccessful();
        self::assertIsArray($response);
        self::assertIsString($response['url'] ?? null);

        return $response['url'];
    }
}
