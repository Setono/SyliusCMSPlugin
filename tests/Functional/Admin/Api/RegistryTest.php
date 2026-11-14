<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

final class RegistryTest extends BuilderApiTestCase
{
    /**
     * @test
     */
    public function it_needs_an_admin_user(): void
    {
        $client = self::createClient();

        $client->request('GET', '/admin/cms/api/registry');

        self::assertResponseRedirects('http://localhost/admin/login');
    }

    /**
     * @test
     */
    public function it_answers_the_block_types_with_their_field_schemas_and_the_ui_strings(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');

        $registry = self::json($client, 'GET', '/admin/cms/api/registry');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertIsArray($registry);
        self::assertIsArray($registry['blockTypes']);

        $heading = self::blockTypes($registry)['heading'];
        self::assertSame('heading', $heading['code']);
        self::assertSame('Heading', $heading['label']);
        self::assertSame(1, $heading['version']);
        self::assertSame([
            [
                'name' => 'text',
                'schema' => [
                    'type' => 'string',
                    'x-cms-options' => ['maxLength' => null],
                    'x-cms-type' => 'text',
                    'x-cms-label' => 'Text',
                    'x-cms-help' => null,
                    'x-cms-required' => true,
                    'x-cms-translatable' => true,
                ],
            ],
            [
                'name' => 'level',
                'schema' => [
                    'type' => 'string',
                    'enum' => ['h1', 'h2', 'h3', 'h4'],
                    'x-cms-options' => [
                        'choices' => ['h1' => 'Heading 1', 'h2' => 'Heading 2', 'h3' => 'Heading 3', 'h4' => 'Heading 4'],
                        'multiple' => false,
                    ],
                    'default' => 'h2',
                    'x-cms-type' => 'choice',
                    'x-cms-label' => 'Level',
                    'x-cms-help' => null,
                    'x-cms-required' => false,
                    'x-cms-translatable' => false,
                ],
            ],
        ], $heading['fields']);

        self::assertSame([
            [
                'name' => 'title',
                'schema' => [
                    'type' => 'string',
                    'x-cms-options' => ['maxLength' => null],
                    'x-cms-type' => 'text',
                    'x-cms-label' => 'Title',
                    'x-cms-help' => null,
                    'x-cms-required' => true,
                    'x-cms-translatable' => true,
                ],
            ],
            [
                'name' => 'content',
                'schema' => [
                    'type' => 'object',
                    'properties' => ['type' => ['const' => 'doc'], 'content' => ['type' => 'array']],
                    'required' => ['type', 'content'],
                    'x-cms-type' => 'rich_text',
                    'x-cms-label' => 'Content',
                    'x-cms-help' => null,
                    'x-cms-required' => false,
                    'x-cms-translatable' => true,
                ],
            ],
        ], self::blockTypes($registry)['accordion_item']['fields']);

        self::assertIsArray($registry['strings']);
        self::assertSame('Saving…', $registry['strings']['setono_sylius_cms.builder.saving'] ?? null);
        self::assertSame('Saved', $registry['strings']['setono_sylius_cms.builder.saved'] ?? null);
        self::assertSame('Publish', $registry['strings']['setono_sylius_cms.builder.publish'] ?? null);
    }

    /**
     * @test
     */
    public function it_answers_what_each_block_type_may_hold_and_where_it_may_sit(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');

        $registry = self::json($client, 'GET', '/admin/cms/api/registry');
        self::assertIsArray($registry);
        $blockTypes = self::blockTypes($registry);

        self::assertSame(['group', 'columns', 'column', 'spacer', 'heading', 'rich_text', 'accordion', 'accordion_item'], array_keys($blockTypes));

        self::assertSame([['type' => 'column', 'min' => 2, 'max' => 4]], $blockTypes['columns']['children']);
        self::assertNull($blockTypes['columns']['parents']);
        self::assertSame(['columns'], $blockTypes['column']['parents']);

        $all = [
            ['type' => 'group', 'min' => null, 'max' => null],
            ['type' => 'columns', 'min' => null, 'max' => null],
            ['type' => 'spacer', 'min' => null, 'max' => null],
            ['type' => 'heading', 'min' => null, 'max' => null],
            ['type' => 'rich_text', 'min' => null, 'max' => null],
            ['type' => 'accordion', 'min' => null, 'max' => null],
        ];
        self::assertSame($all, $blockTypes['group']['children']);
        self::assertSame($all, $blockTypes['column']['children']);
        self::assertSame([], $blockTypes['spacer']['children']);
        self::assertSame([], $blockTypes['heading']['children']);
        self::assertSame([], $blockTypes['rich_text']['children']);

        self::assertSame([['type' => 'accordion_item', 'min' => 1, 'max' => null]], $blockTypes['accordion']['children']);
        self::assertNull($blockTypes['accordion']['parents']);
        self::assertSame(['accordion'], $blockTypes['accordion_item']['parents']);
        self::assertSame([], $blockTypes['accordion_item']['children']);

        self::assertSame('/bundles/setonosyliuscmsplugin/thumbnail/block/columns.svg', $blockTypes['columns']['thumbnail']);
        self::assertFalse($blockTypes['spacer']['disabled']);

        self::assertSame([['code' => 'page', 'label' => 'Page', 'blocks' => $all]], $registry['contentTypes']);
    }

    /**
     * @test
     */
    public function it_answers_the_group_styles_plus_the_one_the_test_app_adds_in_its_config(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');

        $registry = self::json($client, 'GET', '/admin/cms/api/registry');
        self::assertIsArray($registry);
        $blockTypes = self::blockTypes($registry);

        self::assertSame([
            ['code' => 'muted', 'label' => 'Muted'],
            ['code' => 'dark', 'label' => 'Dark'],
            ['code' => 'brand', 'label' => 'Brand'],
        ], $blockTypes['group']['styles']);
        self::assertSame([], $blockTypes['heading']['styles']);
    }

    /**
     * @param array<array-key, mixed> $registry
     *
     * @return array<string, array<string, mixed>> the block types by code
     */
    private static function blockTypes(array $registry): array
    {
        self::assertIsArray($registry['blockTypes']);

        /** @var array<string, array<string, mixed>> $blockTypes */
        $blockTypes = array_column($registry['blockTypes'], null, 'code');

        return $blockTypes;
    }
}
