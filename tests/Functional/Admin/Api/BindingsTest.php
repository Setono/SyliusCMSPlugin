<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Sylius\Component\Product\Model\ProductAttributeInterface;

/**
 * The builder binds fields through the change set, publish checks bindings, the registry lists the bindable
 * properties, and the product attribute binding's attribute is searched among the store's attributes. See "Bindings" in
 * docs/spec/slots.md and docs/spec/builder.md
 */
final class BindingsTest extends BuilderApiTestCase
{
    private const HEADING = '0193a5be-0000-7000-8000-0000000000a1';

    private const BUTTON = '0193a5be-0000-7000-8000-0000000000b1';

    /**
     * @test
     */
    public function it_saves_a_binding_as_a_translatable_field_s_whole_value(): void
    {
        $client = self::createClient();
        $snippet = self::createSnippet('Product intro', [new StoredBlock(self::HEADING, 'heading', 1)], context: ['product']);
        $csrfToken = self::openBuilder($client, $snippet);
        $binding = ['$bind' => 'product.name', '$params' => new \stdClass(), '$fallback' => ['en_US' => 'This product']];

        self::json($client, 'PATCH', '/admin/cms/api/entries/' . $snippet->getUuid(), ['lockToken' => 0, 'changes' => [
            ['op' => 'set_value', 'key' => self::HEADING, 'field' => 'text', 'locale' => null, 'value' => $binding],
        ]], $csrfToken);
        self::assertResponseIsSuccessful();

        $entry = self::json($client, 'GET', '/admin/cms/api/entries/' . $snippet->getUuid());
        self::assertIsArray($entry);
        self::assertSame(['$bind' => 'product.name', '$fallback' => ['en_US' => 'This product'], '$params' => []], $entry['entry']['tree']['blocks'][0]['fields']['text'] ?? null);
        self::assertSame(['errors' => []], self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/validate', $snippet->getUuid())));
    }

    /**
     * A bound link has no target name to show. See "Button" in docs/spec/blocks.md
     *
     * @test
     */
    public function it_refuses_to_publish_a_button_whose_link_is_bound_without_a_label(): void
    {
        $client = self::createClient();
        $snippet = self::createSnippet('Product link', [new StoredBlock(self::BUTTON, 'button', 1)], context: ['product']);
        $csrfToken = self::openBuilder($client, $snippet);
        $link = ['target' => 'url', 'ref' => ['$bind' => 'product.url', '$params' => new \stdClass(), '$fallback' => null], 'anchor' => null, 'label' => new \stdClass()];

        self::json($client, 'PATCH', '/admin/cms/api/entries/' . $snippet->getUuid(), ['lockToken' => 0, 'changes' => [
            ['op' => 'set_value', 'key' => self::BUTTON, 'field' => 'link', 'locale' => null, 'value' => $link],
        ]], $csrfToken);
        self::assertResponseIsSuccessful();

        $refused = self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/publish', $snippet->getUuid()), csrfToken: $csrfToken);
        self::assertResponseStatusCodeSame(422);
        self::assertIsArray($refused);
        self::assertSame([['block' => self::BUTTON, 'field' => 'link', 'locale' => null, 'message' => 'A button that shows a binding, such as the product\'s URL, needs a label.']], $refused['errors'] ?? null);

        self::json($client, 'PATCH', '/admin/cms/api/entries/' . $snippet->getUuid(), ['lockToken' => 1, 'changes' => [
            ['op' => 'set_value', 'key' => self::BUTTON, 'field' => 'link', 'locale' => null, 'value' => ['label' => ['en_US' => 'View product']] + $link],
        ]], $csrfToken);

        self::assertSame(['errors' => []], self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/validate', $snippet->getUuid())));
    }

    /**
     * Bindings count toward the context check. See "Snippets and their context" in docs/spec/slots.md
     *
     * @test
     */
    public function it_refuses_to_publish_a_binding_that_reads_a_context_key_the_entry_does_not_provide(): void
    {
        $client = self::signedIn();
        $page = self::createPageWithBlocks('About us', 'about-us', [new StoredBlock(self::HEADING, 'heading', 1, fields: ['text' => ['$bind' => 'product.name', '$params' => [], '$fallback' => null]])]);

        self::assertSame(['errors' => [
            ['block' => self::HEADING, 'field' => null, 'locale' => null, 'message' => 'The binding "Product name" in the block "Heading" needs the host-page context "Product page".'],
        ]], self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/validate', $page->getUuid())));
    }

    /**
     * @test
     */
    public function it_refuses_to_remove_a_key_from_a_snippet_s_context_while_a_binding_reads_it(): void
    {
        $client = self::createClient();
        $snippet = self::createSnippet('Product intro', [new StoredBlock(self::HEADING, 'heading', 1, fields: ['text' => ['$bind' => 'product.name', '$params' => [], '$fallback' => null]])], context: ['product']);
        $csrfToken = self::openBuilder($client, $snippet);

        $response = self::json($client, 'PATCH', '/admin/cms/api/entries/' . $snippet->getUuid(), ['lockToken' => 0, 'changes' => [
            ['op' => 'set_entry_value', 'field' => 'context', 'locale' => null, 'value' => null],
        ]], $csrfToken);

        self::assertIsArray($response);
        self::assertSame([
            ['block' => null, 'field' => 'context', 'locale' => null, 'message' => 'The binding "Product name" in the block "Heading" needs the host-page context "Product page".'],
        ], $response['refused'] ?? null);
    }

    /**
     * @test
     */
    public function it_lists_the_shipped_bindable_properties_in_the_registry_with_their_types_and_needs(): void
    {
        $client = self::signedIn();

        $registry = self::json($client, 'GET', '/admin/cms/api/registry');

        self::assertIsArray($registry);
        self::assertIsArray($registry['bindableProperties'] ?? null);
        $properties = array_column($registry['bindableProperties'], null, 'binding');
        self::assertSame([
            'product.name', 'product.short_description', 'product.description', 'product.price', 'product.original_price', 'product.code', 'product.url', 'product.main_taxon_name', 'product.attribute',
            'taxon.name', 'taxon.description', 'taxon.url',
            'entry.title', 'entry.url',
            'channel.name', 'channel.contact_email', 'channel.contact_phone',
            'customer.first_name', 'customer.last_name', 'customer.full_name',
        ], array_keys($properties));
        self::assertSame(['binding' => 'product.name', 'label' => 'Product name', 'type' => 'text', 'needs' => ['product'], 'fields' => []], $properties['product.name']);
        self::assertSame('url', $properties['product.url']['type']);
        self::assertSame([], $properties['customer.first_name']['needs']);
        self::assertSame('attribute', $properties['product.attribute']['fields'][0]['name'] ?? null);
        self::assertSame(['text', 'number'], $registry['blockTypes'][array_search('heading', array_column($registry['blockTypes'], 'code'), true)]['fields'][0]['schema']['x-cms-bindable'] ?? null);
    }

    /**
     * @test
     */
    public function it_searches_the_store_s_product_attributes_for_the_product_attribute_binding(): void
    {
        $client = self::signedIn();
        self::createAttribute('material', 'Material');
        self::createAttribute('fit', 'Fit');

        self::assertSame([['ref' => 'material', 'name' => 'Material', 'thumbnail' => null]], self::json($client, 'GET', '/admin/cms/api/search/product_attribute?q=mat'));
        self::assertSame(['fit'], array_column((array) self::json($client, 'GET', '/admin/cms/api/search/product_attribute?refs=fit'), 'ref'));
    }

    private static function createAttribute(string $code, string $name): void
    {
        $attribute = self::getContainer()->get('sylius.fixture.example_factory.product_attribute')->create(['code' => $code, 'name' => $name, 'type' => 'text']);
        self::assertInstanceOf(ProductAttributeInterface::class, $attribute);
        self::entityManager()->persist($attribute);
        self::entityManager()->flush();
    }
}
