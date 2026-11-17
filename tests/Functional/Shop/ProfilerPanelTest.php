<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Setono\SyliusCMSPlugin\Profiler\CMSDataCollector;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\BrokenBlockKernel;
use Setono\SyliusCMSPlugin\Tests\Support\Catalog;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\Placements;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\Taxon;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\Profiler\Profile;

/**
 * The CMS panel of the profiler lists each slot rendered on the page, sub-requests' included, with the context it
 * resolved, every candidate placement and why it didn't show, its render time and the blocks that threw. See "Profiler
 * panel" in docs/spec/rendering.md
 *
 * @phpstan-import-type Slot from CMSDataCollector
 */
final class ProfilerPanelTest extends WebTestCase
{
    use Catalog;
    use Entries;
    use Placements;

    protected static function getKernelClass(): string
    {
        return BrokenBlockKernel::class;
    }

    /**
     * @test
     */
    public function it_lists_the_slots_of_a_product_page_with_their_context_and_every_candidate_placement_with_why_it_did_not_show(): void
    {
        $client = self::createClient();
        self::createProduct('T_SHIRT', 'T-shirt');
        self::createTaxon('SHOES', 'Shoes');
        $shoesOnly = self::place(self::createSnippet('Shoes only', [self::heading('0193a5be-0000-7000-8000-000000000001', 'Shoes')], published: true), 'product.after_price');
        self::scope($shoesOnly, [self::productInTaxon(['SHOES'])]);
        $draft = self::place(self::createSnippet('Draft', [self::heading('0193a5be-0000-7000-8000-000000000002', 'Draft')]), 'product.after_price');
        $sizeGuide = self::place(self::createSnippet('Size guide', [self::heading('0193a5be-0000-7000-8000-000000000003', 'Size guide')], published: true, context: ['product']), 'product.after_price');
        $returns = self::place(self::createSnippet('Free returns', [self::heading('0193a5be-0000-7000-8000-000000000004', 'Free returns')], published: true, context: ['product']), 'product.top');

        $client->enableProfiler();
        $client->request('GET', '/en_US/products/t-shirt');

        self::assertResponseIsSuccessful();
        $slots = self::slots($client);

        $afterPrice = $slots['product.after_price'];
        self::assertSame('setono_sylius_cms.slot.product.after_price', $afterPrice['label']);
        self::assertSame('sylius.shop.product.show.right_sidebar', $afterPrice['templateEvent']);
        self::assertNull($afterPrice['subRequest']);
        self::assertSame([['key' => 'product', 'class' => Product::class, 'id' => 'T_SHIRT', 'passed' => true]], $afterPrice['context']);
        self::assertSame([
            [$shoesOnly->getUuid(), 'Shoes only', 'scope_does_not_match', null],
            [$draft->getUuid(), 'Draft', 'snippet_not_visible', 'not_published'],
            [$sizeGuide->getUuid(), 'Size guide', 'wins', null],
        ], self::candidates($afterPrice));
        self::assertIsFloat($afterPrice['duration']);
        self::assertSame(1, $afterPrice['shown']);

        // A layout event passes no product, so the resolver finds it
        self::assertSame([['key' => 'product', 'class' => Product::class, 'id' => 'T_SHIRT', 'passed' => false]], $slots['product.top']['context']);
        self::assertSame([[$returns->getUuid(), 'Free returns', 'wins', null]], self::candidates($slots['product.top']));

        // A slot without placements resolves no context
        self::assertNull($slots['shop.footer']['context']);
        self::assertNull($slots['shop.footer']['candidates']);
    }

    /**
     * The shop never reads them, so the panel reads them when it collects
     *
     * @test
     */
    public function it_lists_the_placements_whose_snippet_is_not_in_the_channel_with_that_reason(): void
    {
        $client = self::createClient();
        $first = self::place(self::createSnippet('Free shipping', [self::heading('0193a5be-0000-7000-8000-000000000001', 'Free shipping')], published: true), 'shop.footer');
        $danish = self::place(self::createSnippet('Danish shipping', [self::heading('0193a5be-0000-7000-8000-000000000002', 'Danish shipping')], published: true, channels: ['DK']), 'shop.footer');
        $last = self::place(self::createSnippet('Returns', [self::heading('0193a5be-0000-7000-8000-000000000003', 'Returns')], published: true), 'shop.footer');
        $elsewhere = self::place(self::createSnippet('Elsewhere', [self::heading('0193a5be-0000-7000-8000-000000000004', 'Elsewhere')], published: true, channels: []), 'shop.top');

        $client->enableProfiler();
        $client->request('GET', '/en_US/');

        self::assertResponseIsSuccessful();
        $slots = self::slots($client);
        self::assertSame([
            [$first->getUuid(), 'Free shipping', 'wins', null],
            [$danish->getUuid(), 'Danish shipping', 'snippet_not_visible', 'not_in_channel'],
            [$last->getUuid(), 'Returns', 'wins', null],
        ], self::candidates($slots['shop.footer']));
        self::assertSame(2, $slots['shop.footer']['shown']);

        // A slot whose only placements are other channels' resolves no context, but lists them
        self::assertNull($slots['shop.top']['context']);
        self::assertSame([[$elsewhere->getUuid(), 'Elsewhere', 'snippet_not_visible', 'not_in_channel']], self::candidates($slots['shop.top']));
    }

    /**
     * Sylius renders the taxon header in a sub-request, which the main request's panel lists too
     *
     * @test
     */
    public function it_lists_taxon_header_which_renders_in_a_sub_request_of_the_taxon_page(): void
    {
        $client = self::createClient();
        $taxon = self::createTaxon('T_SHIRTS', 'T-shirts');
        self::placeInTaxon(self::createProduct('T_SHIRT', 'T-shirt'), $taxon);
        $banner = self::place(self::createSnippet('Taxon banner', [self::heading('0193a5be-0000-7000-8000-000000000001', 'Our t-shirts')], published: true, context: ['taxon']), 'taxon.header');

        $client->enableProfiler();
        $client->request('GET', '/en_US/taxons/' . $taxon->getSlug());

        self::assertResponseIsSuccessful();
        $header = self::slots($client)['taxon.header'];
        self::assertSame('sylius_shop_partial_taxon_show_by_slug', $header['subRequest']);
        self::assertSame([['key' => 'taxon', 'class' => Taxon::class, 'id' => 'T_SHIRTS', 'passed' => true]], $header['context']);
        self::assertSame([[$banner->getUuid(), 'Taxon banner', 'wins', null]], self::candidates($header));
    }

    /**
     * @test
     */
    public function it_says_which_context_key_a_slot_could_not_resolve(): void
    {
        $client = self::createClient();
        self::place(self::createSnippet('Free returns', published: true, context: ['product']), 'product.top');

        $client->enableProfiler();
        $client->request('GET', '/en_US/');

        $top = self::slots($client)['product.top'];
        self::assertNull($top['context']);
        self::assertSame('product', $top['unresolvedKey']);
        self::assertNull($top['candidates']);
    }

    /**
     * Symfony's profiler collects in the test environment with debug off too, as it would in a production that turns it
     * on, but nothing records the slots there
     *
     * @test
     */
    public function it_has_no_panel_with_debug_off(): void
    {
        $client = self::createClient(['debug' => false]);
        self::place(self::createSnippet('Footer', [self::heading('0193a5be-0000-7000-8000-000000000001', 'Footer')], published: true), 'shop.footer');

        $client->enableProfiler();
        $client->request('GET', '/en_US/');

        self::assertResponseIsSuccessful();
        $profile = $client->getProfile();
        self::assertInstanceOf(Profile::class, $profile);
        self::assertFalse($profile->hasCollector(CMSDataCollector::NAME), 'The panel is there in debug only');
    }

    /**
     * @test
     */
    public function it_lists_the_block_that_threw_in_a_slot_before_the_exception_breaks_the_page_in_dev(): void
    {
        $client = self::createClient();
        $snippet = self::createSnippet('Footer', [new StoredBlock('0193a5be-0000-7000-8000-000000000002', 'broken', 1)], published: true);
        self::place($snippet, 'shop.footer');

        $client->enableProfiler();
        $client->request('GET', '/en_US/');

        self::assertResponseStatusCodeSame(500);
        self::assertSame([[
            'entry' => $snippet->getUuid(),
            'block' => '0193a5be-0000-7000-8000-000000000002',
            'blockType' => 'broken',
            'placement' => null,
            'message' => 'Setono\SyliusCMSPlugin\Tests\Functional\Fixture\Broken has no field "missing"',
        ]], self::slots($client)['shop.footer']['failures']);
    }

    /**
     * @return array<string, Slot>
     */
    private static function slots(KernelBrowser $client): array
    {
        $profile = $client->getProfile();
        self::assertInstanceOf(Profile::class, $profile);
        $collector = $profile->getCollector(CMSDataCollector::NAME);
        self::assertInstanceOf(CMSDataCollector::class, $collector);

        $slots = [];
        foreach ($collector->getSlots() as $slot) {
            $slots[$slot['identifier']] = $slot;
        }

        return $slots;
    }

    /**
     * @param Slot $slot
     *
     * @return list<array{string, string, string, string|null}> placement, title, outcome, reason
     */
    private static function candidates(array $slot): array
    {
        return array_map(static fn (array $candidate): array => [$candidate['placement'], $candidate['title'], $candidate['outcome'], $candidate['reason']], $slot['candidates'] ?? []);
    }
}
