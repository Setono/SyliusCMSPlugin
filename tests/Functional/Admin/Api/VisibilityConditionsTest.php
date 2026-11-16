<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Setono\SyliusCMSPlugin\Tests\Support\Customers;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;

/**
 * What the builder needs to edit a block's visibility conditions: the condition kinds with their parameters' fields in
 * the registry, the set_conditions change, the preview leaving out the blocks they hide, and the customer group search.
 * See "Right pane: the selection" and "JSON endpoints" in docs/spec/builder.md
 */
final class VisibilityConditionsTest extends BuilderApiTestCase
{
    use Customers;

    private const KEY = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f';

    /**
     * @test
     */
    public function the_registry_has_the_condition_kinds_with_their_parameters_as_shared_fields_with_the_stores_choices(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');
        self::createCustomerGroup('wholesale', 'Wholesale');

        $registry = self::json($client, 'GET', '/admin/cms/api/registry');

        self::assertResponseIsSuccessful();
        self::assertIsArray($registry);
        self::assertIsArray($registry['conditionKinds'] ?? null);
        $kinds = array_column($registry['conditionKinds'], null, 'code');
        self::assertSame(['channel', 'locale', 'date_window', 'customer_group'], array_keys($kinds));
        self::assertSame(['Channel', 'Language', 'Date window', 'Customer group'], array_column($registry['conditionKinds'], 'label'));

        self::assertSame([
            'name' => 'channels',
            'schema' => [
                'type' => 'array',
                'items' => ['type' => 'string', 'enum' => ['WEB']],
                'uniqueItems' => true,
                'x-cms-options' => ['choices' => ['WEB' => 'Setono CMS Test Store'], 'multiple' => true],
                'default' => [],
                'x-cms-type' => 'choice',
                'x-cms-label' => 'Channels',
                'x-cms-help' => null,
                'x-cms-required' => false,
                'x-cms-translatable' => false,
            ],
        ], $kinds['channel']['fields'][0]);
        self::assertSame(['en_US' => 'English (United States)'], $kinds['locale']['fields'][0]['schema']['x-cms-options']['choices']);
        self::assertSame(['@guest' => 'Guests', '@no_group' => 'Signed in without a group', 'wholesale' => 'Wholesale'], $kinds['customer_group']['fields'][0]['schema']['x-cms-options']['choices']);
        self::assertSame(['from', 'until'], array_column($kinds['date_window']['fields'], 'name'));
        self::assertSame('date_time', $kinds['date_window']['fields'][0]['schema']['x-cms-type']);
    }

    /**
     * @test
     */
    public function it_saves_a_blocks_conditions_with_dates_and_times_in_utc(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        $csrfToken = self::openBuilder($client, $page);

        self::json($client, 'PATCH', '/admin/cms/api/entries/' . $page->getUuid(), ['lockToken' => 0, 'changes' => [
            ['op' => 'set_conditions', 'key' => '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f', 'conditions' => [
                ['kind' => 'locale', 'negate' => true, 'params' => ['locales' => ['da_DK']]],
                ['kind' => 'date_window', 'negate' => false, 'params' => ['from' => '2026-11-27T01:00:00+01:00']],
            ]],
        ]], $csrfToken);

        self::assertResponseIsSuccessful();
        self::assertSame([
            ['kind' => 'locale', 'negate' => true, 'params' => ['locales' => ['da_DK']]],
            ['kind' => 'date_window', 'negate' => false, 'params' => ['from' => '2026-11-27T00:00:00Z']],
        ], self::reloadPage($page)->getBlockTree()->blocks[0]->conditions);
    }

    /**
     * @test
     */
    public function it_refuses_conditions_that_are_not_in_the_condition_format(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        $csrfToken = self::openBuilder($client, $page);

        $response = self::json($client, 'PATCH', '/admin/cms/api/entries/' . $page->getUuid(), ['lockToken' => 0, 'changes' => [
            ['op' => 'set_conditions', 'key' => self::KEY, 'conditions' => [['kind' => 'locale', 'negate' => 'yes', 'params' => []]]],
        ]], $csrfToken);

        self::assertResponseStatusCodeSame(400);
        self::assertIsArray($response);
        self::assertSame('The negate of a condition must be true or false', $response['error'] ?? null);
        self::assertSame([], self::reloadPage($page)->getBlockTree()->blocks[0]->conditions);
    }

    /**
     * A hidden block renders nothing, so the preview drops it
     *
     * @test
     */
    public function the_preview_renders_nothing_for_a_block_its_conditions_hide(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us', 'Hello');
        $csrfToken = self::openBuilder($client, $page);
        $block = static fn (bool $negate): array => [
            'key' => self::KEY,
            'type' => 'heading',
            'version' => 1,
            'fields' => ['text' => ['en_US' => 'Hello']],
            'conditions' => [['kind' => 'locale', 'negate' => $negate, 'params' => ['locales' => ['en_US']]]],
        ];

        $response = self::json($client, 'POST', '/admin/cms/api/preview/render?_cms_preview=' . self::previewToken($page), ['blocks' => [$block(true)]], $csrfToken);
        self::assertResponseIsSuccessful();
        self::assertSame(['blocks' => [self::KEY => '']], $response);

        $response = self::json($client, 'POST', '/admin/cms/api/preview/render?_cms_preview=' . self::previewToken($page), ['blocks' => [$block(false)]], $csrfToken);
        self::assertIsArray($response);
        self::assertIsArray($response['blocks'] ?? null);
        self::assertStringContainsString('Hello', (string) $response['blocks'][self::KEY]);
    }

    /**
     * @test
     */
    public function the_customer_group_search_finds_guests_customers_without_a_group_and_the_stores_groups_by_name_or_code(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');
        self::createCustomerGroup('wholesale', 'Wholesale');
        self::createCustomerGroup('vip', 'Very important');

        $all = self::json($client, 'GET', '/admin/cms/api/search/customer_group');
        self::assertResponseIsSuccessful();
        self::assertSame([
            ['ref' => '@guest', 'name' => 'Guests', 'thumbnail' => null],
            ['ref' => '@no_group', 'name' => 'Signed in without a group', 'thumbnail' => null],
            ['ref' => 'vip', 'name' => 'Very important', 'thumbnail' => null],
            ['ref' => 'wholesale', 'name' => 'Wholesale', 'thumbnail' => null],
        ], $all);

        self::assertSame([['ref' => 'wholesale', 'name' => 'Wholesale', 'thumbnail' => null]], self::json($client, 'GET', '/admin/cms/api/search/customer_group?q=SALE'));
        self::assertSame([['ref' => 'vip', 'name' => 'Very important', 'thumbnail' => null]], self::json($client, 'GET', '/admin/cms/api/search/customer_group?q=vip'));
        self::assertSame(['@guest'], array_column((array) self::json($client, 'GET', '/admin/cms/api/search/customer_group?q=guest'), 'ref'));
    }

    /**
     * @test
     */
    public function the_search_answers_404_for_a_kind_it_cannot_search_and_needs_an_admin_user(): void
    {
        $client = self::createClient();

        $client->request('GET', '/admin/cms/api/search/customer_group');
        self::assertResponseRedirects('http://localhost/admin/login');

        $client->loginUser(self::admin(), 'admin');
        $client->request('GET', '/admin/cms/api/search/spaceship');
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @test
     */
    public function a_block_of_any_type_keeps_its_conditions_when_it_is_published(): void
    {
        $client = self::createClient();
        $block = new StoredBlock(self::KEY, 'group', 1, conditions: [['kind' => 'channel', 'negate' => false, 'params' => ['channels' => ['WEB']]]]);
        $page = self::createPageWithBlocks('About us', 'about-us', [$block]);
        $csrfToken = self::openBuilder($client, $page);

        self::json($client, 'POST', sprintf('/admin/cms/api/entries/%s/publish', $page->getUuid()), [], $csrfToken);

        self::assertResponseIsSuccessful();
        self::assertSame([['kind' => 'channel', 'negate' => false, 'params' => ['channels' => ['WEB']]]], self::reloadPage($page)->getBlockTree()->blocks[0]->conditions);
    }
}
