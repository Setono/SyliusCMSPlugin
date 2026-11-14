<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Setono\SyliusCMSPlugin\Tree\StoredBlock;

/**
 * Saving sanitises rich text against the schema. See "Rich text" in docs/spec/content-model.md
 */
final class RichTextTest extends BuilderApiTestCase
{
    private const KEY = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f';

    private const NEW_KEY = '0193a5be-70aa-7c11-8e2d-1b2c3d4e5f60';

    /**
     * @test
     */
    public function saving_a_document_with_content_the_schema_does_not_have_stores_the_sanitised_document(): void
    {
        $client = self::createClient();
        $page = self::createPageWithBlocks('Story', 'story', [new StoredBlock(self::KEY, 'rich_text', 1)]);
        $csrfToken = self::openBuilder($client, $page);

        self::json($client, 'PATCH', '/admin/cms/api/entries/' . $page->getUuid(), ['lockToken' => 0, 'changes' => [
            ['op' => 'set_value', 'key' => self::KEY, 'field' => 'content', 'locale' => 'en_US', 'value' => ['type' => 'doc', 'content' => [
                ['type' => 'heading', 'attrs' => ['level' => 1, 'id' => 'top'], 'content' => [['type' => 'text', 'text' => 'Title']]],
                ['type' => 'paragraph', 'attrs' => ['style' => 'color: red'], 'content' => [
                    ['type' => 'text', 'text' => 'Hello ', 'marks' => [['type' => 'highlight', 'attrs' => ['color' => 'red']]]],
                    ['type' => 'text', 'text' => 'world', 'marks' => [['type' => 'bold']]],
                    ['type' => 'mention', 'attrs' => ['id' => 'jane']],
                ]],
                ['type' => 'iframe', 'attrs' => ['src' => 'https://example.com']],
            ]]],
        ]], $csrfToken);

        self::assertResponseIsSuccessful();
        self::assertSame(
            sprintf(
                '{"layout":{"code":"standard","version":1,"fields":{},"translated":{}},"blocks":[{"key":"%s","type":"rich_text","version":1,"style":null,"fields":{"content":{"en_US":{"content":[{"content":[{"text":"Title","type":"text"}],"type":"paragraph"},{"content":[{"text":"Hello ","type":"text"},{"marks":[{"type":"bold"}],"text":"world","type":"text"}],"type":"paragraph"}],"type":"doc"}}},"translated":{},"conditions":[],"children":[]}]}',
                self::KEY,
            ),
            self::reloadPage($page)->getBlockTreeJson(),
        );
    }

    /**
     * @test
     */
    public function adding_a_block_with_rich_text_stores_it_sanitised(): void
    {
        $client = self::createClient();
        $page = self::createPage('Story', 'story');
        $csrfToken = self::openBuilder($client, $page);

        self::json($client, 'PATCH', '/admin/cms/api/entries/' . $page->getUuid(), ['lockToken' => 0, 'changes' => [
            ['op' => 'add_block', 'parent' => null, 'index' => 0, 'block' => [
                'key' => self::NEW_KEY,
                'type' => 'rich_text',
                'version' => 1,
                'fields' => ['content' => ['en_US' => ['type' => 'doc', 'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hi', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']]]]]],
                ]]]],
            ]],
        ]], $csrfToken);

        self::assertResponseIsSuccessful();
        // In canonical JSON's order: ProseMirror nodes have their keys sorted
        self::assertSame(
            ['en_US' => ['content' => [['content' => [['text' => 'Hi', 'type' => 'text']], 'type' => 'paragraph']], 'type' => 'doc']],
            self::reloadPage($page)->getBlockTree()->blocks[0]->fields['content'] ?? null,
        );
    }
}
