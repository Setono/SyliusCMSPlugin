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

        self::assertSame(['group', 'columns', 'column', 'spacer', 'heading', 'rich_text', 'image', 'video', 'button', 'accordion', 'accordion_item', 'link_list', 'link', 'downloads', 'products', 'taxons', 'first_match'], array_keys($blockTypes));

        self::assertSame([['type' => 'column', 'min' => 2, 'max' => 4]], $blockTypes['columns']['children']);
        self::assertNull($blockTypes['columns']['parents']);
        self::assertSame(['columns'], $blockTypes['column']['parents']);

        $all = [
            ['type' => 'group', 'min' => null, 'max' => null],
            ['type' => 'columns', 'min' => null, 'max' => null],
            ['type' => 'spacer', 'min' => null, 'max' => null],
            ['type' => 'heading', 'min' => null, 'max' => null],
            ['type' => 'rich_text', 'min' => null, 'max' => null],
            ['type' => 'image', 'min' => null, 'max' => null],
            ['type' => 'video', 'min' => null, 'max' => null],
            ['type' => 'button', 'min' => null, 'max' => null],
            ['type' => 'accordion', 'min' => null, 'max' => null],
            ['type' => 'link_list', 'min' => null, 'max' => null],
            ['type' => 'downloads', 'min' => null, 'max' => null],
            ['type' => 'products', 'min' => null, 'max' => null],
            ['type' => 'taxons', 'min' => null, 'max' => null],
            ['type' => 'first_match', 'min' => null, 'max' => null],
        ];
        self::assertSame($all, $blockTypes['group']['children']);
        self::assertSame($all, $blockTypes['column']['children']);
        self::assertSame($all, $blockTypes['first_match']['children']);
        self::assertSame([], $blockTypes['first_match']['fields']);
        self::assertSame([], $blockTypes['spacer']['children']);
        self::assertSame([], $blockTypes['heading']['children']);
        self::assertSame([], $blockTypes['rich_text']['children']);

        self::assertSame([['type' => 'accordion_item', 'min' => 1, 'max' => null]], $blockTypes['accordion']['children']);
        self::assertNull($blockTypes['accordion']['parents']);
        self::assertSame(['accordion'], $blockTypes['accordion_item']['parents']);
        self::assertSame([], $blockTypes['accordion_item']['children']);

        self::assertSame([['type' => 'link', 'min' => null, 'max' => null]], $blockTypes['link_list']['children']);
        self::assertSame(['link_list'], $blockTypes['link']['parents']);
        self::assertSame([], $blockTypes['link']['children']);

        self::assertSame('/bundles/setonosyliuscmsplugin/thumbnail/block/columns.svg', $blockTypes['columns']['thumbnail']);
        self::assertFalse($blockTypes['spacer']['disabled']);

        // The test application adds a content type of its own, without a URL and so without layouts, and a layout of its own to pages
        self::assertIsArray($registry['contentTypes']);
        self::assertSame(['code' => 'announcement', 'label' => 'Announcement', 'blocks' => $all, 'prefix' => null, 'homepage' => false, 'layouts' => []], $registry['contentTypes'][0]);
        $page = $registry['contentTypes'][1];
        self::assertIsArray($page);
        self::assertSame(['page', 'Page', $all, '', true], [$page['code'], $page['label'], $page['blocks'], $page['prefix'], $page['homepage']]);
        self::assertIsArray($page['layouts']);
        $layouts = [];
        foreach ($page['layouts'] as $layout) {
            self::assertIsArray($layout);
            self::assertIsArray($layout['fields']);
            $layouts[] = [$layout['code'], $layout['label'], $layout['thumbnail'], $layout['default'], $layout['shows'], array_column($layout['fields'], 'name')];
        }
        self::assertSame([
            ['standard', 'Standard', '/bundles/setonosyliuscmsplugin/thumbnail/layout/page/standard.svg', true, ['title'], ['showTitle']],
            ['landing', 'Landing', null, false, [], ['tagline', 'transparentHeader']],
        ], $layouts);
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
     * @test
     */
    public function it_answers_the_link_targets_the_shipped_ones_first_and_the_button_with_its_link_and_styles(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');

        $registry = self::json($client, 'GET', '/admin/cms/api/registry');
        self::assertIsArray($registry);

        self::assertSame([
            ['code' => 'entry', 'label' => 'Entry', 'translatableRef' => false],
            ['code' => 'product', 'label' => 'Product', 'translatableRef' => false],
            ['code' => 'taxon', 'label' => 'Taxon', 'translatableRef' => false],
            ['code' => 'media', 'label' => 'Document or video', 'translatableRef' => false],
            ['code' => 'url', 'label' => 'URL', 'translatableRef' => true],
            ['code' => 'app_store', 'label' => 'Store', 'translatableRef' => false],
        ], $registry['linkTargets']);

        $button = self::blockTypes($registry)['button'];
        self::assertSame('Button', $button['label']);
        self::assertSame([['code' => 'primary', 'label' => 'Primary'], ['code' => 'secondary', 'label' => 'Secondary']], $button['styles']);
        self::assertIsArray($button['fields']);
        $link = $button['fields'][0] ?? null;
        self::assertIsArray($link);
        self::assertSame('link', $link['name']);
        $schema = $link['schema'];
        self::assertIsArray($schema);
        self::assertSame(['link', 'Link', true, false], [$schema['x-cms-type'], $schema['x-cms-label'], $schema['x-cms-required'], $schema['x-cms-translatable']]);
        self::assertSame(['targets' => ['entry', 'product', 'taxon', 'media', 'url', 'app_store'], 'translatable' => null], $schema['x-cms-options']);
    }

    /**
     * @test
     */
    public function it_answers_the_image_block_with_a_required_image_an_alt_text_and_a_caption_per_language_and_a_link(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');

        $registry = self::json($client, 'GET', '/admin/cms/api/registry');
        self::assertIsArray($registry);

        $image = self::blockTypes($registry)['image'];
        self::assertSame('Image', $image['label']);
        self::assertSame('/bundles/setonosyliuscmsplugin/thumbnail/block/image.svg', $image['thumbnail']);
        self::assertSame([], $image['children']);
        self::assertIsArray($image['fields']);

        $fields = [];
        foreach ($image['fields'] as $field) {
            self::assertIsArray($field);
            self::assertIsArray($field['schema']);
            self::assertIsString($field['name']);
            $fields[$field['name']] = [$field['schema']['x-cms-type'], $field['schema']['x-cms-label'], $field['schema']['x-cms-required'], $field['schema']['x-cms-translatable']];
        }

        self::assertSame([
            'image' => ['media', 'Image', true, false],
            'alt' => ['text', 'Alt text', false, true],
            'caption' => ['text', 'Caption', false, true],
            'link' => ['link', 'Link', false, false],
        ], $fields);
    }

    /**
     * @test
     */
    public function it_answers_the_products_and_taxons_blocks_with_their_list_fields(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');

        $registry = self::json($client, 'GET', '/admin/cms/api/registry');
        self::assertIsArray($registry);
        $blockTypes = self::blockTypes($registry);

        self::assertSame(['Products', 'Taxons'], [$blockTypes['products']['label'], $blockTypes['taxons']['label']]);
        // Compared regardless of key order
        self::assertEquals(
            [
                [
                    'name' => 'products',
                    'schema' => [
                        'type' => 'array',
                        'items' => ['type' => 'string', 'minLength' => 1],
                        'x-cms-options' => ['max' => null, 'sources' => true, 'limit' => 8],
                        'x-cms-type' => 'products',
                        'x-cms-label' => 'Products',
                        'x-cms-help' => null,
                        'x-cms-required' => false,
                        'x-cms-translatable' => false,
                    ],
                ],
                [
                    'name' => 'display',
                    'schema' => [
                        'type' => 'string',
                        'enum' => ['grid'],
                        'x-cms-options' => ['choices' => ['grid' => 'Grid'], 'multiple' => false],
                        'x-cms-type' => 'choice',
                        'x-cms-label' => 'Display',
                        'x-cms-help' => null,
                        'x-cms-required' => false,
                        'x-cms-translatable' => false,
                        'default' => 'grid',
                    ],
                ],
            ],
            $blockTypes['products']['fields'],
        );

        $taxons = $blockTypes['taxons']['fields'];
        self::assertIsArray($taxons);
        self::assertIsArray($taxons[0]);
        self::assertSame('taxons', $taxons[0]['name']);
        self::assertIsArray($taxons[0]['schema']);
        self::assertSame(['taxons', 'Taxons'], [$taxons[0]['schema']['x-cms-type'], $taxons[0]['schema']['x-cms-label']]);
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
