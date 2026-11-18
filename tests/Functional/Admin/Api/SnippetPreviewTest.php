<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\SlotsKernel;
use Setono\SyliusCMSPlugin\Tests\Support\Catalog;
use Setono\SyliusCMSPlugin\Tests\Support\Html;
use Setono\SyliusCMSPlugin\Tests\Support\Placements;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;

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
     * Caps shop.top at 1, as a project caps a stock slot
     */
    protected static function getKernelClass(): string
    {
        return SlotsKernel::class;
    }

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
     * The top bar warns when the sample object is outside the placement's page scope
     *
     * @test
     */
    public function it_tells_the_builder_the_sample_object_and_whether_it_is_inside_the_placement_s_scope(): void
    {
        $client = self::createClient();
        self::createProduct('T_SHIRT', 'T-shirt');
        self::createProduct('MUG', 'Mug');
        $snippet = self::createSnippet('Mug care', [self::heading(self::KEY, 'Rinse after use')], context: ['product']);
        $placement = self::place($snippet, 'product.after_price');
        self::scope($placement, [['kind' => 'product', 'negate' => false, 'params' => ['products' => ['MUG']]]]);
        $csrfToken = self::openBuilder($client, $snippet);

        $starting = self::previewUrl($client, $csrfToken, ['entry' => $snippet->getUuid(), 'placement' => $placement->getUuid()]);
        self::assertSame(
            ['sample' => ['key' => 'product', 'ref' => 'MUG', 'name' => 'Mug', 'matches' => true], 'shownInstead' => null],
            self::json($client, 'GET', '/admin/cms/api/preview/snippet?_cms_preview=' . self::tokenOf($starting)),
        );

        $outside = self::previewUrl($client, $csrfToken, ['entry' => $snippet->getUuid(), 'placement' => $placement->getUuid(), 'sample' => 'T_SHIRT']);
        self::assertSame(
            ['sample' => ['key' => 'product', 'ref' => 'T_SHIRT', 'name' => 'T-shirt', 'matches' => false], 'shownInstead' => null],
            self::json($client, 'GET', '/admin/cms/api/preview/snippet?_cms_preview=' . self::tokenOf($outside)),
        );

        // The snippet still shows there, so the editor sees it
        $client->request('GET', $outside);
        self::assertSelectorTextSame('[data-cms-slot-preview="product.after_price"] .cms-heading', 'Rinse after use');
    }

    /**
     * @test
     */
    public function it_lists_the_sample_objects_the_picker_finds_marking_those_inside_the_placement_s_scope(): void
    {
        $client = self::createClient();
        self::createProduct('T_SHIRT', 'T-shirt');
        self::createProduct('MUG', 'Mug');
        self::createProduct('CAP', 'Cap', enabled: false);
        $snippet = self::createSnippet('Mug care', context: ['product']);
        $placement = self::place($snippet, 'product.after_price');
        self::scope($placement, [['kind' => 'product', 'negate' => false, 'params' => ['products' => ['MUG']]]]);
        $csrfToken = self::openBuilder($client, $snippet);
        $token = self::tokenOf(self::previewUrl($client, $csrfToken, ['entry' => $snippet->getUuid(), 'placement' => $placement->getUuid()]));

        self::assertSame([
            ['ref' => 'MUG', 'name' => 'Mug', 'thumbnail' => null, 'matches' => true],
            ['ref' => 'T_SHIRT', 'name' => 'T-shirt', 'thumbnail' => null, 'matches' => false],
        ], self::json($client, 'GET', '/admin/cms/api/preview/samples?_cms_preview=' . $token), 'A disabled product has no page to show');

        self::assertSame(
            [['ref' => 'T_SHIRT', 'name' => 'T-shirt', 'thumbnail' => null, 'matches' => false]],
            self::json($client, 'GET', '/admin/cms/api/preview/samples?q=shirt&_cms_preview=' . $token),
        );
    }

    /**
     * See "Shown to and Date" in docs/spec/targeting.md
     *
     * @test
     */
    public function it_shows_a_snippet_that_loses_its_capped_slot_there_and_names_the_snippet_shoppers_see(): void
    {
        $client = self::createClient();
        self::place(self::createSnippet('Free shipping', [self::heading('0193a5be-0000-7000-8000-000000000002', 'Free shipping')], published: true), 'shop.top');
        $sale = self::createSnippet('Winter sale', [self::heading(self::KEY, 'Winter sale')], published: true);
        $placement = self::place($sale, 'shop.top');
        $csrfToken = self::openBuilder($client, $sale);

        $url = self::previewUrl($client, $csrfToken, ['entry' => $sale->getUuid(), 'placement' => $placement->getUuid()]);

        self::assertMatchesRegularExpression('#^http://localhost/en_US/\?_cms_preview=#', $url, 'shop.top is on every page');
        $crawler = $client->request('GET', $url);
        self::assertSame(['Winter sale'], $crawler->filter('.cms-slot--shop-top .cms-heading')->each(static fn (Crawler $heading): string => $heading->text()), 'It shows there as if it won');
        self::assertSame(
            ['sample' => null, 'shownInstead' => ['Free shipping']],
            self::json($client, 'GET', '/admin/cms/api/preview/snippet?_cms_preview=' . self::tokenOf($url)),
        );

        $client->request('GET', '/en_US/');
        self::assertSelectorTextSame('.cms-slot--shop-top .cms-heading', 'Free shipping', 'Shoppers still see the winner');
    }

    /**
     * taxon.header fires in a Sylius sub-request, and preview state is read from the main request. See "Preview mode
     * and share links" in docs/spec/rendering.md
     *
     * @test
     */
    public function it_previews_a_placement_in_taxon_header_in_its_sub_request_and_patches_it_with_the_sample_taxon(): void
    {
        $client = self::createClient();
        $shoes = self::createTaxon('SHOES', 'Shoes');
        $snippet = self::createSnippet('Taxon intro', [new StoredBlock(self::KEY, 'heading', 1, fields: ['text' => ['$bind' => 'taxon.name', '$params' => [], '$fallback' => ['en_US' => 'This taxon']]])], context: ['taxon']);
        $placement = self::place($snippet, 'taxon.header');
        $csrfToken = self::openBuilder($client, $snippet);

        $url = self::previewUrl($client, $csrfToken, ['entry' => $snippet->getUuid(), 'placement' => $placement->getUuid()]);

        self::assertStringStartsWith(sprintf('http://localhost/en_US/taxons/%s?_cms_preview=', $shoes->getSlug()), $url);
        $crawler = $client->request('GET', $url);
        self::assertSame(
            sprintf('<!--cms:block %s heading--><h2 class="cms-heading">Shoes</h2><!--/cms:block %1$s-->', self::KEY),
            Html::withoutWhitespaceBetweenTags($crawler->filter('[data-cms-slot-preview="taxon.header"] .cms-slot--taxon-header')->html()),
        );

        $response = self::json($client, 'POST', '/admin/cms/api/preview/render?_cms_preview=' . self::tokenOf($url), ['blocks' => [[
            'key' => self::KEY, 'type' => 'heading', 'version' => 1,
            'fields' => ['text' => ['$bind' => 'taxon.name', '$params' => [], '$fallback' => ['en_US' => 'Our taxon']]],
        ]]], $csrfToken);
        self::assertStringContainsString('<h2 class="cms-heading">Shoes</h2>', self::renderedBlock($response));
    }

    /**
     * A snippet's share link fixes the placement and the sample object, and its URL is the host page's. It renders like
     * the shop, with no markers and no outline. See "Share links" in docs/spec/publishing.md
     *
     * @test
     */
    public function it_shows_a_snippet_s_share_link_on_the_host_page_of_its_placement(): void
    {
        $client = self::createClient();
        self::createProduct('T_SHIRT', 'T-shirt');
        self::createProduct('MUG', 'Mug');
        $snippet = self::createSnippet('Mug care', [self::heading(self::KEY, 'Rinse after use')], context: ['product']);
        $placement = self::place($snippet, 'product.after_price');
        self::scope($placement, [['kind' => 'product', 'negate' => false, 'params' => ['products' => ['MUG']]]]);
        $csrfToken = self::openBuilder($client, $snippet);

        $shareLink = self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/share-links', $snippet->getUuid()), ['channel' => 'WEB', 'locale' => 'en_US', 'placement' => $placement->getUuid()], $csrfToken);

        self::assertResponseStatusCodeSame(201);
        self::assertIsArray($shareLink);
        self::assertSame($placement->getUuid(), $shareLink['placement'] ?? null);
        self::assertSame('MUG', $shareLink['sample'] ?? null);
        self::assertIsString($shareLink['url'] ?? null);
        self::assertMatchesRegularExpression('#^http://localhost/en_US/products/mug\?_cms_share=[A-Za-z0-9_-]+$#', $shareLink['url']);

        $client->getCookieJar()->clear();
        $crawler = $client->request('GET', $shareLink['url']);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('<h2 class="cms-heading">Rinse after use</h2>', Html::withoutWhitespaceBetweenTags($crawler->filter('.cms-slot--product-after-price')->html()), 'The draft, though the snippet was never published');
        self::assertSelectorNotExists('[data-cms-slot-preview]');
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
     * Only an entry in a layout whose template renders the slot shows it, so the preview starts with one
     *
     * @test
     */
    public function it_previews_a_placement_in_a_layout_s_slot_on_the_page_of_an_entry_in_that_layout(): void
    {
        $client = self::createClient(server: ['HTTP_HOST' => 'admin.example']);
        self::createPage('About us', 'about-us', 'Who we are', published: true);
        $promo = self::createPage('Winter sale', 'winter-sale', 'Everything must go', published: true);
        $promo->setBlockTree(new StoredTree(new StoredLayout('promo', 1), $promo->getBlockTree()->blocks));
        self::entityManager()->flush();
        $snippet = self::createSnippet('More like this', [self::heading(self::KEY, 'More like this')], context: ['entry']);
        $placement = self::place($snippet, 'app.promo.after_blocks');
        $csrfToken = self::openBuilder($client, $snippet);

        $url = self::previewUrl($client, $csrfToken, ['entry' => $snippet->getUuid(), 'placement' => $placement->getUuid()]);

        self::assertMatchesRegularExpression('#^http://admin\.example/en_US/winter-sale\?_cms_preview=#', $url, 'Not About us, which comes first by name, but whose layout doesn\'t render the slot');
        $client->request('GET', $url);
        self::assertSelectorTextSame('[data-cms-slot-preview="app.promo.after_blocks"] .cms-heading', 'More like this');
    }

    /**
     * @test
     */
    public function it_previews_a_placement_in_a_layout_s_slot_on_a_plain_page_when_no_entry_is_in_that_layout(): void
    {
        $client = self::createClient(server: ['HTTP_HOST' => 'admin.example']);
        self::createPage('About us', 'about-us', 'Who we are', published: true);
        $snippet = self::createSnippet('More like this', [self::heading(self::KEY, 'More like this')], context: ['entry']);
        $placement = self::place($snippet, 'app.promo.after_blocks');
        $csrfToken = self::openBuilder($client, $snippet);

        $url = self::previewUrl($client, $csrfToken, ['entry' => $snippet->getUuid(), 'placement' => $placement->getUuid()]);

        self::assertMatchesRegularExpression('#^http://admin\.example/en_US/_cms/snippet-preview\?_cms_preview=#', $url);
        $client->request('GET', $url);
        self::assertSelectorTextSame('[data-cms-slot-preview="app.promo.after_blocks"] .cms-heading', 'More like this');
    }

    /**
     * A CMS homepage replaces Sylius's, whose template renders store_homepage
     *
     * @test
     */
    public function it_previews_a_placement_in_store_homepage_on_a_plain_page_when_the_channel_s_homepage_is_a_cms_page(): void
    {
        $client = self::createClient(server: ['HTTP_HOST' => 'admin.example']);
        $snippet = self::createSnippet('Welcome', [self::heading(self::KEY, 'Welcome')]);
        $placement = self::place($snippet, 'store_homepage');
        $csrfToken = self::openBuilder($client, $snippet);

        self::assertMatchesRegularExpression('#^http://admin\.example/en_US/\?_cms_preview=#', self::previewUrl($client, $csrfToken, ['entry' => $snippet->getUuid(), 'placement' => $placement->getUuid()]));

        self::setHomepageOf(self::createPage('Home', 'home', 'Our store', published: true), 'WEB');
        $url = self::previewUrl($client, $csrfToken, ['entry' => $snippet->getUuid(), 'placement' => $placement->getUuid()]);

        self::assertMatchesRegularExpression('#^http://admin\.example/en_US/_cms/snippet-preview\?_cms_preview=#', $url);
        $client->request('GET', $url);
        self::assertSelectorTextSame('[data-cms-slot-preview="store_homepage"] .cms-heading', 'Welcome');
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
        $token = self::tokenOf($url);

        $response = self::json($client, 'POST', '/admin/cms/api/preview/render?_cms_preview=' . $token, ['blocks' => [[
            'key' => self::KEY, 'type' => 'heading', 'version' => 1,
            'fields' => ['text' => ['$bind' => 'product.name', '$params' => [], '$fallback' => ['en_US' => 'This product']]],
        ]]], $csrfToken);

        self::assertResponseIsSuccessful();
        self::assertSame(sprintf('<!--cms:block %s heading--><h2 class="cms-heading">Mug</h2><!--/cms:block %1$s-->', self::KEY), Html::withoutWhitespaceBetweenTags(self::renderedBlock($response)));
    }

    /**
     * A heading bound to the product's name, which shows "This product" without one
     */
    private static function productName(): StoredBlock
    {
        return new StoredBlock(self::KEY, 'heading', 1, fields: ['text' => ['$bind' => 'product.name', '$params' => [], '$fallback' => ['en_US' => 'This product']]]);
    }

    /**
     * The HTML preview_render answers for the block with the key KEY
     */
    private static function renderedBlock(mixed $response): string
    {
        self::assertIsArray($response);
        self::assertIsArray($response['blocks'] ?? null);
        $html = $response['blocks'][self::KEY] ?? null;
        self::assertIsString($html);

        return $html;
    }

    /**
     * The preview token in the URL of a preview
     */
    private static function tokenOf(string $url): string
    {
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
        $token = $query['_cms_preview'] ?? null;
        self::assertIsString($token);

        return $token;
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
