<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\ListSourcesKernel;

/**
 * The registry gives the builder the sources editors may pick, the shipped ones first and then a project's, with their
 * parameters, and each list field names those it offers. A disabled source is missing from it. See "Sources" in
 * docs/spec/builder.md
 */
final class SourcesInTheRegistryTest extends BuilderApiTestCase
{
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

        self::assertSame(['products_in_taxon', 'taxon_children', 'featured'], array_keys($sources));
        self::assertSame('In taxon', $sources['products_in_taxon']['label']);
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

        self::assertSame(['max' => null, 'sources' => ['products_in_taxon', 'featured'], 'limit' => 8], $blockTypes['products']['fields'][0]['schema']['x-cms-options'] ?? null);
        self::assertSame(['max' => null, 'sources' => ['taxon_children'], 'limit' => 8], $blockTypes['taxons']['fields'][0]['schema']['x-cms-options'] ?? null);
    }
}
