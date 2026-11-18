<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional;

use Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api\BuilderApiTestCase;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\EmbedCodeOffKernel;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;

/**
 * The Embed code block with setono_sylius_cms.embed_code off, as in a project that hasn't opted in: editors can't add
 * it, publish refuses one whose key isn't already in the entry's published tree, however it got into the draft, or
 * whose code differs from the published one's, and blocks published while it was on keep rendering, and can be moved
 * or removed. See "Embed code" in docs/spec/blocks.md and ADR 0018
 */
final class EmbedCodeSwitchedOffTest extends BuilderApiTestCase
{
    private const EMBED_CODE = '0193a5be-0000-7000-8000-0000000000e1';

    private const HEADING = '0193a5be-0000-7000-8000-0000000000e2';

    private const GROUP = '0193a5be-0000-7000-8000-0000000000e3';

    private const CODE = '<div id="reviews"></div><script>document.documentElement.dataset.embedMarker = "set";</script>';

    private const REFUSED = 'Embed code is switched off in this shop, so a new Embed code block can\'t be published. Remove it.';

    private const CHANGED = 'Embed code is switched off in this shop, so the code of an Embed code block can\'t be changed. Undo the change, or remove the block.';

    protected static function getKernelClass(): string
    {
        return EmbedCodeOffKernel::class;
    }

    /**
     * @test
     */
    public function it_marks_the_block_type_disabled_so_the_picker_leaves_it_out(): void
    {
        $client = self::signedIn();

        $registry = self::json($client, 'GET', '/admin/cms/api/registry');

        self::assertIsArray($registry);
        self::assertIsArray($registry['blockTypes']);
        $disabled = array_column($registry['blockTypes'], 'disabled', 'code');
        self::assertTrue($disabled['embed_code']);
        self::assertFalse($disabled['heading']);
    }

    /**
     * @test
     */
    public function it_refuses_to_publish_a_crafted_draft_with_a_new_embed_code_block(): void
    {
        $client = self::createClient();
        $page = self::createPageWithBlocks('Reviews', 'reviews', [self::heading(self::HEADING, 'Reviews')], published: true);
        $rows = self::reloadPage($page)->getBlockTreeJson();
        $csrfToken = self::openBuilder($client, $page);
        self::json($client, 'PATCH', '/admin/cms/api/entries/' . $page->getUuid(), ['lockToken' => 0, 'changes' => [
            ['op' => 'add_block', 'parent' => null, 'index' => 1, 'block' => ['key' => self::EMBED_CODE, 'type' => 'embed_code', 'version' => 1, 'fields' => ['code' => self::CODE]]],
        ]], $csrfToken);
        self::assertResponseIsSuccessful();

        $response = self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/publish', $page->getUuid()), null, $csrfToken);

        self::assertResponseStatusCodeSame(422);
        self::assertIsArray($response);
        self::assertSame([['block' => self::EMBED_CODE, 'field' => null, 'locale' => null, 'message' => self::REFUSED]], $response['errors'] ?? null);
        self::assertSame($rows, self::reloadPage($page)->getBlockTreeJson());
        $client->request('GET', '/en_US/reviews');
        self::assertSelectorNotExists('.cms-embed-code');
    }

    /**
     * @test
     */
    public function it_flags_a_new_embed_code_block_of_an_entry_never_published_in_the_builders_validation(): void
    {
        $client = self::createClient();
        $page = self::createPageWithBlocks('Reviews', 'reviews', [self::embedCode()]);
        self::openBuilder($client, $page);

        $response = self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/validate', $page->getUuid()));

        self::assertResponseIsSuccessful();
        self::assertSame(['errors' => [['block' => self::EMBED_CODE, 'field' => null, 'locale' => null, 'message' => self::REFUSED]]], $response);
    }

    /**
     * @test
     */
    public function it_keeps_rendering_an_embed_code_block_published_while_it_was_on(): void
    {
        $client = self::createClient();
        self::createPageWithBlocks('Reviews', 'reviews', [self::embedCode()], published: true);

        $client->request('GET', '/en_US/reviews');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('<div class="cms-embed-code">' . self::CODE . '</div>', (string) $client->getResponse()->getContent());
    }

    /**
     * @test
     */
    public function it_publishes_a_draft_that_keeps_an_embed_code_block_published_while_it_was_on(): void
    {
        $client = self::createClient();
        $page = self::createPageWithBlocks('Reviews', 'reviews', [self::embedCode()], published: true);
        $csrfToken = self::openBuilder($client, $page);
        self::saveChanges($page, [['op' => 'add_block', 'parent' => null, 'index' => 0, 'block' => ['key' => self::HEADING, 'type' => 'heading', 'version' => 1, 'fields' => ['text' => ['en_US' => 'Reviews']]]]]);

        self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/publish', $page->getUuid()), null, $csrfToken);

        self::assertResponseIsSuccessful();
        $client->request('GET', '/en_US/reviews');
        self::assertSelectorExists('.cms-tree > .cms-heading + .cms-embed-code > #reviews');
    }

    /**
     * @test
     */
    public function it_refuses_to_publish_a_changed_code_of_an_embed_code_block_published_while_it_was_on(): void
    {
        $client = self::createClient();
        $page = self::createPageWithBlocks('Reviews', 'reviews', [self::embedCode()], published: true);
        $csrfToken = self::openBuilder($client, $page);
        self::saveChanges($page, [['op' => 'set_value', 'key' => self::EMBED_CODE, 'field' => 'code', 'locale' => null, 'value' => '<script>document.documentElement.dataset.skimmer = "set";</script>']]);

        $response = self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/publish', $page->getUuid()), null, $csrfToken);

        self::assertResponseStatusCodeSame(422);
        self::assertIsArray($response);
        self::assertSame([['block' => self::EMBED_CODE, 'field' => 'code', 'locale' => null, 'message' => self::CHANGED]], $response['errors'] ?? null);
        $client->request('GET', '/en_US/reviews');
        self::assertStringContainsString('<div class="cms-embed-code">' . self::CODE . '</div>', (string) $client->getResponse()->getContent());
    }

    /**
     * @test
     */
    public function it_publishes_a_draft_that_moves_an_embed_code_block_published_while_it_was_on_into_another_block_or_removes_it(): void
    {
        $client = self::createClient();
        $page = self::createPageWithBlocks('Reviews', 'reviews', [self::embedCode(), new StoredBlock(self::GROUP, 'group', 1)], published: true);
        $csrfToken = self::openBuilder($client, $page);
        self::saveChanges($page, [['op' => 'move_block', 'key' => self::EMBED_CODE, 'parent' => self::GROUP, 'index' => 0]]);

        self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/publish', $page->getUuid()), null, $csrfToken);

        self::assertResponseIsSuccessful();
        $client->request('GET', '/en_US/reviews');
        self::assertSelectorExists('.cms-tree .cms-group .cms-embed-code > #reviews');

        self::saveChanges(self::reloadPage($page), [['op' => 'remove_block', 'key' => self::EMBED_CODE]]);
        self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/publish', $page->getUuid()), null, $csrfToken);

        self::assertResponseIsSuccessful();
        $client->request('GET', '/en_US/reviews');
        self::assertSelectorNotExists('.cms-embed-code');
    }

    private static function embedCode(): StoredBlock
    {
        return new StoredBlock(self::EMBED_CODE, 'embed_code', 1, fields: ['code' => self::CODE]);
    }
}
