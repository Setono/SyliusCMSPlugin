<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Placements;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;

/**
 * A snippet declares the host-page context it needs, publish refuses what needs context the entry doesn't provide, and
 * a snippet's declared context can't change while something depends on it. See "Snippets and their context" in
 * docs/spec/slots.md and "Validation" in docs/spec/publishing.md
 */
final class HostPageContextTest extends BuilderApiTestCase
{
    use Placements;

    private const NOTE = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f';

    /**
     * The test application's Product note needs the product, which a page doesn't provide
     *
     * @test
     */
    public function it_refuses_to_publish_a_page_with_a_block_that_needs_the_product_and_says_why(): void
    {
        $client = self::signedIn();
        $page = self::createPageWithBlocks('About us', 'about-us', [self::note()]);

        $response = self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/validate', $page->getUuid()));

        self::assertResponseIsSuccessful();
        self::assertSame(['errors' => [
            ['block' => self::NOTE, 'field' => null, 'locale' => null, 'message' => 'The block "Product note" needs the host-page context "Product page".'],
        ]], $response);
    }

    /**
     * @test
     */
    public function it_publishes_a_snippet_that_declares_the_product_its_blocks_need(): void
    {
        $client = self::signedIn();
        $snippet = self::createSnippet('Size guide', [self::note()], context: ['product']);
        self::place($snippet, 'product.after_price');

        $response = self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/validate', $snippet->getUuid()));

        self::assertResponseIsSuccessful();
        self::assertSame(['errors' => []], $response);
    }

    /**
     * @test
     */
    public function it_saves_the_context_a_snippet_declares_in_its_working_copy(): void
    {
        $client = self::createClient();
        $snippet = self::createSnippet('Size guide');
        $csrfToken = self::openBuilder($client, $snippet);

        $response = self::json($client, 'PATCH', '/admin/cms/api/entries/' . $snippet->getUuid(), ['lockToken' => 0, 'changes' => [
            ['op' => 'set_entry_value', 'field' => 'context', 'locale' => null, 'value' => ['product']],
        ]], $csrfToken);

        self::assertResponseIsSuccessful();
        self::assertSame(['status' => 'never_published', 'lockToken' => 1], $response);
        $entry = self::json($client, 'GET', '/admin/cms/api/entries/' . $snippet->getUuid());
        self::assertIsArray($entry);
        self::assertIsArray($entry['entry'] ?? null);
        self::assertSame(['context' => ['product']], $entry['entry']['fields'] ?? null);
    }

    /**
     * Adding a key is blocked by a placement in a slot that doesn't provide it, and the save lists what's in the way and
     * carries the working copy, which keeps its context
     *
     * @test
     */
    public function it_refuses_to_change_a_snippet_s_context_while_a_placement_is_in_the_way_and_lists_it(): void
    {
        $client = self::createClient();
        $snippet = self::createSnippet('Free shipping', published: true);
        self::place($snippet, 'shop.footer');
        $csrfToken = self::openBuilder($client, $snippet);

        $response = self::json($client, 'PATCH', '/admin/cms/api/entries/' . $snippet->getUuid(), ['lockToken' => 0, 'changes' => [
            ['op' => 'set_entry_value', 'field' => 'context', 'locale' => null, 'value' => ['product']],
        ]], $csrfToken);

        self::assertResponseIsSuccessful();
        self::assertIsArray($response);
        self::assertSame([
            ['block' => null, 'field' => 'context', 'locale' => null, 'message' => 'The placement in "Every page: the footer" doesn\'t provide the host-page context "Product page".'],
        ], $response['refused'] ?? null);
        self::assertSame(0, $response['lockToken'] ?? null, 'Nothing else changed, so nothing saved');
        self::assertIsArray($response['entry'] ?? null);
        self::assertSame(['context' => []], $response['entry']['fields'] ?? null);
        self::assertSame([], self::reloadSnippet($snippet)->getContext());
    }

    /**
     * Removing a key is blocked by the blocks that need it
     *
     * @test
     */
    public function it_refuses_to_remove_a_key_from_a_snippet_s_context_while_a_block_needs_it(): void
    {
        $client = self::createClient();
        $snippet = self::createSnippet('Size guide', [self::note()], context: ['product']);
        $csrfToken = self::openBuilder($client, $snippet);

        $response = self::json($client, 'PATCH', '/admin/cms/api/entries/' . $snippet->getUuid(), ['lockToken' => 0, 'changes' => [
            ['op' => 'set_entry_value', 'field' => 'context', 'locale' => null, 'value' => null],
        ]], $csrfToken);

        self::assertIsArray($response);
        self::assertSame([
            ['block' => null, 'field' => 'context', 'locale' => null, 'message' => 'The block "Product note" needs the host-page context "Product page".'],
        ], $response['refused'] ?? null);
        self::assertSame(['product'], self::reloadSnippet($snippet)->getContext());
    }

    private static function reloadSnippet(SnippetInterface $snippet): SnippetInterface
    {
        self::entityManager()->clear();
        $reloaded = self::entityManager()->find($snippet::class, $snippet->getId());
        self::assertInstanceOf(SnippetInterface::class, $reloaded);

        return $reloaded;
    }

    private static function note(): StoredBlock
    {
        $block = new StoredBlock(self::NOTE, 'app_product_note', 1);
        $block->setValue('text', 'en_US', 'Size guide for');

        return $block;
    }
}
