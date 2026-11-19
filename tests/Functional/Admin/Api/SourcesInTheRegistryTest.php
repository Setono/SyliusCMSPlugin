<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\ListSourcesKernel;
use Setono\SyliusCMSPlugin\Tests\Support\Catalog;

/**
 * The registry gives the builder the sources editors may pick, the shipped ones first and then a project's, with their
 * parameters, and each list field names those it offers. A disabled source is missing from it. See "Sources" in
 * docs/spec/builder.md
 */
final class SourcesInTheRegistryTest extends BuilderApiTestCase
{
    use Catalog;

    protected static function getKernelClass(): string
    {
        return ListSourcesKernel::class;
    }

    /**
     * @test
     */
    public function it_answers_the_sources_editors_may_pick_with_their_parameters_but_not_a_disabled_one(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');

        $registry = self::json($client, 'GET', '/admin/cms/api/registry');

        self::assertResponseIsSuccessful();
        self::assertIsArray($registry);
        self::assertIsArray($registry['sources'] ?? null);
        /** @var array<string, array{code: string, label: string, needs: list<string>, fields: list<array{name: string, schema: array<string, mixed>}>}> $sources */
        $sources = array_column($registry['sources'], null, 'code');

        self::assertSame(['products_in_taxon', 'bestsellers', 'product_associations', 'same_taxon_products', 'taxon_children', 'entries_from_collection', 'entries_in_this_category', 'related_entries', 'featured'], array_keys($sources));
        self::assertSame('In taxon', $sources['products_in_taxon']['label']);
        self::assertSame('Bestsellers', $sources['bestsellers']['label']);
        self::assertSame('Children of a taxon', $sources['taxon_children']['label']);
        self::assertSame([], $sources['products_in_taxon']['needs']);
        self::assertSame(['taxon', 'includeChildren', 'sort'], array_column($sources['products_in_taxon']['fields'], 'name'));

        [$taxon, $includeChildren, $sort] = array_column($sources['products_in_taxon']['fields'], 'schema');
        self::assertSame(['taxon', 'Taxon', true, false], [$taxon['x-cms-type'], $taxon['x-cms-label'], $taxon['x-cms-required'], $taxon['x-cms-translatable']]);
        self::assertSame(['toggle', 'Include the taxons below it', false], [$includeChildren['x-cms-type'], $includeChildren['x-cms-label'], $includeChildren['default'] ?? null]);
        self::assertSame(['choice', 'Sort by', 'position'], [$sort['x-cms-type'], $sort['x-cms-label'], $sort['default'] ?? null]);
        self::assertSame(
            ['position' => 'Position in the taxon', 'newest' => 'Newest first', 'price' => 'Price, lowest first', 'name' => 'Name, A to Z'],
            is_array($sort['x-cms-options'] ?? null) ? $sort['x-cms-options']['choices'] ?? null : null,
        );

        // Bestsellers' taxon is optional
        [$taxon, $includeChildren] = array_column($sources['bestsellers']['fields'], 'schema');
        self::assertSame(['taxon', 'includeChildren'], array_column($sources['bestsellers']['fields'], 'name'));
        self::assertSame(['taxon', 'Taxon', false], [$taxon['x-cms-type'], $taxon['x-cms-label'], $taxon['x-cms-required']]);
        self::assertSame(['toggle', 'Include the taxons below it', false], [$includeChildren['x-cms-type'], $includeChildren['x-cms-label'], $includeChildren['default'] ?? null]);
    }

    /**
     * In this category and Related fit only some content types as the entry, which the builder reads, and From a
     * collection's categories are those of the collection chosen (docs/spec/sources.md#entry-sources)
     *
     * @test
     */
    public function it_answers_the_entry_sources_with_the_content_types_they_fit_and_from_a_collection_s_collections(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');

        $registry = self::json($client, 'GET', '/admin/cms/api/registry');
        self::assertIsArray($registry);
        self::assertIsArray($registry['sources'] ?? null);
        /** @var array<string, array{code: string, label: string, needs: list<string>, entryContentTypes: list<string>|null, fields: list<array{name: string, schema: array<string, mixed>}>}> $sources */
        $sources = array_column($registry['sources'], null, 'code');

        self::assertSame(['From a collection', [], null], [$sources['entries_from_collection']['label'], $sources['entries_from_collection']['needs'], $sources['entries_from_collection']['entryContentTypes']]);
        self::assertSame(['In this category', ['entry'], ['blog_category', 'faq_category']], [$sources['entries_in_this_category']['label'], $sources['entries_in_this_category']['needs'], $sources['entries_in_this_category']['entryContentTypes']]);
        self::assertSame(['Related', ['entry'], ['blog_post', 'faq_item']], [$sources['related_entries']['label'], $sources['related_entries']['needs'], $sources['related_entries']['entryContentTypes']]);
        self::assertNull($sources['products_in_taxon']['entryContentTypes']);

        [$contentType, $categories] = array_column($sources['entries_from_collection']['fields'], 'schema');
        self::assertSame(['choice', 'Collection', true], [$contentType['x-cms-type'], $contentType['x-cms-label'], $contentType['x-cms-required']]);
        self::assertSame(['blog_post' => 'Blog', 'faq_item' => 'FAQ'], array_slice(is_array($contentType['x-cms-options'] ?? null) && is_array($contentType['x-cms-options']['choices'] ?? null) ? $contentType['x-cms-options']['choices'] : [], 0, 2));
        self::assertSame(['entries', 'Categories'], [$categories['x-cms-type'], $categories['x-cms-label']]);
        self::assertSame(['max' => null, 'sources' => [], 'limit' => 8, 'contentType' => null, 'categoriesOf' => 'contentType'], $categories['x-cms-options'] ?? null);
    }

    /**
     * They're offered only where a product is available, which the builder reads from their needs
     *
     * @test
     */
    public function it_answers_associations_and_same_taxon_as_needing_the_product_with_the_store_s_association_types_to_pick_from(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');
        self::createAssociationType('SIMILAR', 'Similar products');
        self::createAssociationType('ACCESSORIES', 'Accessories');

        $registry = self::json($client, 'GET', '/admin/cms/api/registry');
        self::assertIsArray($registry);
        self::assertIsArray($registry['sources'] ?? null);
        /** @var array<string, array{code: string, label: string, needs: list<string>, fields: list<array{name: string, schema: array<string, mixed>}>}> $sources */
        $sources = array_column($registry['sources'], null, 'code');

        self::assertSame(['Associations', ['product']], [$sources['product_associations']['label'], $sources['product_associations']['needs']]);
        self::assertSame(['Same taxon', ['product'], []], [$sources['same_taxon_products']['label'], $sources['same_taxon_products']['needs'], $sources['same_taxon_products']['fields']]);

        // The association type is optional, picked from the store's types by name, and left empty for every type
        self::assertSame(['associationType'], array_column($sources['product_associations']['fields'], 'name'));
        $associationType = $sources['product_associations']['fields'][0]['schema'];
        self::assertSame(['choice', 'Association type', false], [$associationType['x-cms-type'], $associationType['x-cms-label'], $associationType['x-cms-required']]);
        self::assertSame('Leave it empty to list the products of every association type.', $associationType['x-cms-help']);
        self::assertSame(['choices' => ['ACCESSORIES' => 'Accessories', 'SIMILAR' => 'Similar products'], 'multiple' => false], $associationType['x-cms-options'] ?? null);
    }

    /**
     * @test
     */
    public function it_names_in_each_list_field_s_schema_the_sources_it_offers_and_where_it_starts(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');

        $registry = self::json($client, 'GET', '/admin/cms/api/registry');
        self::assertIsArray($registry);
        self::assertIsArray($registry['blockTypes'] ?? null);
        /** @var array<string, array{fields: list<array{name: string, schema: array<string, mixed>}>}> $blockTypes */
        $blockTypes = array_column($registry['blockTypes'], null, 'code');

        self::assertSame(['max' => null, 'sources' => ['products_in_taxon', 'bestsellers', 'product_associations', 'same_taxon_products', 'featured'], 'limit' => 8], $blockTypes['products']['fields'][0]['schema']['x-cms-options'] ?? null);
        self::assertSame(['max' => null, 'sources' => ['taxon_children'], 'limit' => 8], $blockTypes['taxons']['fields'][0]['schema']['x-cms-options'] ?? null);
    }
}
