<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Setono\SyliusCMSPlugin\Tests\Support\Catalog;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;

/**
 * A Link field as the builder saves it, through JSON, where a label in no locale is an empty object, and as publish
 * validates it. See "Links" in docs/spec/content-model.md and "Validation" in docs/spec/publishing.md
 */
final class LinkFieldTest extends BuilderApiTestCase
{
    use Catalog;

    private const BUTTON = '0193a5be-0000-7000-8000-0000000000b1';

    /**
     * @test
     */
    public function it_validates_and_publishes_a_link_the_builder_saved_without_a_label(): void
    {
        $client = self::createClient();
        self::createProduct('T_SHIRT_RED', 'Red T-shirt');
        $page = self::createPageWithBlocks('Shop now', 'shop-now', [new StoredBlock(self::BUTTON, 'button', 1)]);
        $csrfToken = self::openBuilder($client, $page);

        self::json($client, 'PATCH', '/admin/cms/api/entries/' . $page->getUuid(), ['lockToken' => 0, 'changes' => [
            ['op' => 'set_value', 'key' => self::BUTTON, 'field' => 'link', 'locale' => null, 'value' => ['target' => 'product', 'ref' => 'T_SHIRT_RED', 'anchor' => null, 'label' => new \stdClass()]],
        ]], $csrfToken);
        self::assertResponseIsSuccessful();

        self::assertSame(['errors' => []], self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/validate', $page->getUuid())));

        self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/publish', $page->getUuid()), csrfToken: $csrfToken);
        self::assertResponseIsSuccessful();

        $client->request('GET', '/en_US/shop-now');
        self::assertSelectorTextSame('.cms-tree > a.cms-button', 'Red T-shirt');
    }

    /**
     * @test
     */
    public function publish_refuses_a_button_without_a_link_and_a_url_a_link_can_not_lead_to(): void
    {
        $client = self::createClient();
        $page = self::createPageWithBlocks('Shop now', 'shop-now', [new StoredBlock(self::BUTTON, 'button', 1)]);
        $csrfToken = self::openBuilder($client, $page);

        self::assertSame(['errors' => [['block' => self::BUTTON, 'field' => 'link', 'locale' => null, 'message' => 'This field is required.']]], self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/validate', $page->getUuid())));

        self::json($client, 'PATCH', '/admin/cms/api/entries/' . $page->getUuid(), ['lockToken' => 0, 'changes' => [
            ['op' => 'set_value', 'key' => self::BUTTON, 'field' => 'link', 'locale' => null, 'value' => ['target' => 'url', 'ref' => ['en_US' => 'javascript:alert(1)'], 'anchor' => null, 'label' => ['en_US' => 'Click me']]],
        ]], $csrfToken);

        self::assertSame(
            ['errors' => [['block' => self::BUTTON, 'field' => 'link', 'locale' => null, 'message' => 'A link can\'t lead to this URL. Start it with https://, http://, mailto:, tel: or /.']]],
            self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/validate', $page->getUuid())),
        );
    }
}
