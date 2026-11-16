<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional;

use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Preview\Preview;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyManagerInterface;
use Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api\BuilderApiTestCase;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\UpgradesKernel;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;

/**
 * Stored data is upgraded whenever it's read: from the rows and from drafts. The test app's Callout block type is at
 * version 2, where its field "text" became "message". See "Schema changes" in docs/spec/content-model.md
 */
final class UpgradeTest extends BuilderApiTestCase
{
    private const CALLOUT = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e60';

    private const HEADING = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f';

    protected static function getKernelClass(): string
    {
        return UpgradesKernel::class;
    }

    /**
     * @test
     */
    public function it_renders_a_block_saved_with_an_older_version_through_its_upgrader_and_leaves_the_rows_as_they_are(): void
    {
        $client = self::createClient();
        $page = self::createPageWithBlocks('About us', 'about-us', [self::calloutAtVersion1('Saved with version 1')], published: true);
        $rows = $page->getBlockTreeJson();

        $client->request('GET', '/en_US/about-us');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('.cms-tree .app-callout', 'Saved with version 1');
        self::assertSame($rows, self::reloadPage($page)->getBlockTreeJson());
    }

    /**
     * @test
     */
    public function it_upgrades_a_block_saved_with_an_older_version_in_a_draft_in_the_preview_the_builder_and_on_publish(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);
        self::saveDraft($page, [self::calloutAtVersion1('Drafted with version 1')]);
        self::assertStringContainsString('"type":"callout","version":1,', (string) self::draftOf($page)?->getContent());

        // The preview renders the draft
        $client->request('GET', '/en_US/about-us?_cms_preview=' . self::previewToken($page));
        self::assertSelectorTextSame('.cms-tree .app-callout', 'Drafted with version 1');

        // The builder gets it upgraded
        $csrfToken = self::openBuilder($client, $page);
        $response = self::json($client, 'GET', '/admin/cms/api/entries/' . $page->getUuid());
        self::assertIsArray($response);
        self::assertIsArray($response['entry'] ?? null);
        self::assertIsArray($response['entry']['tree'] ?? null);
        self::assertSame(
            [['key' => self::CALLOUT, 'type' => 'callout', 'version' => 2, 'style' => null, 'fields' => ['message' => ['en_US' => 'Drafted with version 1']], 'translated' => [], 'conditions' => [], 'children' => []]],
            $response['entry']['tree']['blocks'] ?? null,
        );

        // Publishing writes it upgraded to the rows
        self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/publish', $page->getUuid()), null, $csrfToken);
        self::assertResponseIsSuccessful();
        self::assertSame(
            sprintf('{"layout":{"code":"standard","version":1,"fields":{},"translated":{}},"blocks":[{"key":"%s","type":"callout","version":2,"style":null,"fields":{"message":{"en_US":"Drafted with version 1"}},"translated":{},"conditions":[],"children":[]}]}', self::CALLOUT),
            self::reloadPage($page)->getBlockTreeJson(),
        );

        $client->request('GET', '/en_US/about-us');
        self::assertSelectorTextSame('.cms-tree .app-callout', 'Drafted with version 1');
    }

    /**
     * @test
     */
    public function it_ignores_a_removed_fields_value_and_drops_it_on_the_next_save_which_writes_older_blocks_upgraded(): void
    {
        $client = self::createClient();
        $heading = self::heading(self::HEADING, 'Hello');
        $heading->setValue('subtitle', 'en_US', 'A field the code no longer has');
        $page = self::createPageWithBlocks('About us', 'about-us', [$heading, self::calloutAtVersion1('Saved with version 1')]);
        self::assertStringContainsString('"subtitle"', $page->getBlockTreeJson());

        $client->request('GET', '/en_US/about-us?_cms_preview=' . self::previewToken($page));
        self::assertSelectorTextSame('.cms-tree .cms-heading', 'Hello');

        $csrfToken = self::openBuilder($client, $page);
        self::json($client, 'PATCH', '/admin/cms/api/entries/' . $page->getUuid(), ['lockToken' => 0, 'changes' => [
            ['op' => 'set_value', 'key' => self::HEADING, 'field' => 'text', 'locale' => 'en_US', 'value' => 'Hello world'],
        ]], $csrfToken);
        self::assertResponseIsSuccessful();

        self::assertSame(
            sprintf('{"layout":{"code":"standard","version":1,"fields":{},"translated":{}},"blocks":[{"key":"%s","type":"heading","version":1,"style":null,"fields":{"text":{"en_US":"Hello world"}},"translated":{},"conditions":[],"children":[]},{"key":"%s","type":"callout","version":2,"style":null,"fields":{"message":{"en_US":"Saved with version 1"}},"translated":{},"conditions":[],"children":[]}]}', self::HEADING, self::CALLOUT),
            self::reloadPage($page)->getBlockTreeJson(),
        );
    }

    /**
     * @test
     */
    public function it_renders_a_block_the_builder_holds_at_an_older_version_in_the_preview_through_its_upgrader(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        $csrfToken = self::openBuilder($client, $page);

        $response = self::json($client, 'POST', '/admin/cms/api/preview/render?_cms_preview=' . self::previewToken($page), ['blocks' => [
            ['key' => self::CALLOUT, 'type' => 'callout', 'version' => 1, 'style' => null, 'fields' => ['text' => ['en_US' => 'Held at version 1']], 'translated' => (object) [], 'conditions' => [], 'children' => []],
        ]], $csrfToken);

        self::assertResponseIsSuccessful();
        self::assertIsArray($response);
        self::assertIsArray($response['blocks'] ?? null);
        self::assertIsString($response['blocks'][self::CALLOUT] ?? null);
        self::assertStringContainsString(
            sprintf('<p class="app-callout"><!--cms:field %1$s message text-->Held at version 1<!--/cms:field %1$s message--></p>', self::CALLOUT),
            $response['blocks'][self::CALLOUT],
            'The old value renders, and is marked for typing in place under the field\'s current name',
        );
    }

    /**
     * A Callout block as version 1 stored it, when its "message" was called "text"
     */
    private static function calloutAtVersion1(string $text): StoredBlock
    {
        return new StoredBlock(self::CALLOUT, 'callout', 1, fields: ['text' => ['en_US' => $text]]);
    }

    /**
     * Saves these blocks to the published page's draft, as stored
     *
     * @param list<StoredBlock> $blocks
     */
    private static function saveDraft(PageInterface $page, array $blocks): void
    {
        /** @var WorkingCopyManagerInterface $workingCopies */
        $workingCopies = self::getContainer()->get(WorkingCopyManagerInterface::class);
        $workingCopy = $workingCopies->getWorkingCopy($page);
        $workingCopy->setBlockTree(new StoredTree($workingCopy->getBlockTree()->layout, $blocks));
        $workingCopies->save($page, $workingCopy);
    }
}
