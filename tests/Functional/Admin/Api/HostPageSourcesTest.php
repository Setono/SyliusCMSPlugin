<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Setono\SyliusCMSPlugin\Tests\Support\Placements;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;

/**
 * Associations and Same taxon need the host page's product: a page doesn't provide it, so publish refuses them there,
 * and a snippet that lists them must declare it. In this category and Related need the entry, and fit only some content
 * types as it. See "The contract" in docs/spec/sources.md and "Snippets and their
 * context" in docs/spec/slots.md
 */
final class HostPageSourcesTest extends BuilderApiTestCase
{
    use Placements;

    private const ASSOCIATIONS = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e61';

    private const SAME_TAXON = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e62';

    /**
     * @test
     */
    public function it_refuses_to_publish_a_page_with_lists_filled_by_associations_or_same_taxon_and_says_why(): void
    {
        $client = self::signedIn();
        $page = self::createPageWithBlocks('About us', 'about-us', [
            self::products(self::ASSOCIATIONS, 'product_associations'),
            self::products(self::SAME_TAXON, 'same_taxon_products'),
        ]);

        $response = self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/validate', $page->getUuid()));

        self::assertResponseIsSuccessful();
        self::assertSame(['errors' => [
            ['block' => self::ASSOCIATIONS, 'field' => null, 'locale' => null, 'message' => 'The source "Associations" in the block "Products" needs the host-page context "Product page".'],
            ['block' => self::SAME_TAXON, 'field' => null, 'locale' => null, 'message' => 'The source "Same taxon" in the block "Products" needs the host-page context "Product page".'],
        ]], $response);
    }

    /**
     * @test
     */
    public function it_publishes_a_snippet_for_product_pages_with_lists_filled_by_associations_and_same_taxon(): void
    {
        $client = self::signedIn();
        $snippet = self::createSnippet('You may also like', [
            self::products(self::ASSOCIATIONS, 'product_associations'),
            self::products(self::SAME_TAXON, 'same_taxon_products'),
        ], context: ['product']);
        self::place($snippet, 'product.after_price');

        $response = self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/validate', $snippet->getUuid()));

        self::assertResponseIsSuccessful();
        self::assertSame(['errors' => []], $response);
    }

    /**
     * @test
     */
    public function it_refuses_to_remove_the_product_from_a_snippet_s_context_while_a_list_is_filled_by_same_taxon(): void
    {
        $client = self::createClient();
        $snippet = self::createSnippet('More like this', [self::products(self::SAME_TAXON, 'same_taxon_products')], context: ['product']);
        $csrfToken = self::openBuilder($client, $snippet);

        $response = self::json($client, 'PATCH', '/admin/cms/api/entries/' . $snippet->getUuid(), ['lockToken' => 0, 'changes' => [
            ['op' => 'set_entry_value', 'field' => 'context', 'locale' => null, 'value' => null],
        ]], $csrfToken);

        self::assertIsArray($response);
        self::assertSame([
            ['block' => null, 'field' => 'context', 'locale' => null, 'message' => 'The source "Same taxon" in the block "Products" needs the host-page context "Product page".'],
        ], $response['refused'] ?? null);
    }

    /**
     * In this category fits only a category as the entry, and Related only an entry of a collection with categories, so
     * a page provides the entry they need but isn't one they fit (docs/spec/extending.md#sources)
     *
     * @test
     */
    public function it_refuses_to_publish_a_page_with_lists_filled_by_in_this_category_or_related_and_says_why(): void
    {
        $client = self::signedIn();
        $page = self::createPageWithBlocks('About us', 'about-us', [
            new StoredBlock(self::ASSOCIATIONS, 'entries', 1, fields: ['entries' => ['$source' => 'entries_in_this_category', '$params' => [], '$limit' => 9]]),
            new StoredBlock(self::SAME_TAXON, 'entries', 1, fields: ['entries' => ['$source' => 'related_entries', '$params' => [], '$limit' => 3]]),
        ]);

        $response = self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/validate', $page->getUuid()));

        self::assertResponseIsSuccessful();
        self::assertSame(['errors' => [
            ['block' => self::ASSOCIATIONS, 'field' => null, 'locale' => null, 'message' => 'The source "In this category" in the block "Entries" only works in an entry of the type "Blog category", "FAQ category".'],
            ['block' => self::SAME_TAXON, 'field' => null, 'locale' => null, 'message' => 'The source "Related" in the block "Entries" only works in an entry of the type "Blog post", "FAQ item".'],
        ]], $response);
    }

    private static function products(string $key, string $source): StoredBlock
    {
        return new StoredBlock($key, 'products', 1, fields: ['products' => ['$source' => $source, '$params' => [], '$limit' => 4]]);
    }
}
