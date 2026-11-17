<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Catalog;

/**
 * source_count: how many items a source yields right now for the parameters the builder holds, in the preview's
 * channel, locale, Shown to and Date, which the builder shows next to "Filled by". See "Sources" and "JSON endpoints" in
 * docs/spec/builder.md
 */
final class SourceCountTest extends BuilderApiTestCase
{
    use Catalog;

    /**
     * @test
     */
    public function it_counts_every_item_the_source_yields_in_the_preview_s_channel(): void
    {
        $client = self::createClient();
        self::createChannel('OUTLET');
        $shirts = self::createTaxon('T_SHIRTS', 'T-shirts');
        foreach (['BASIC', 'PLAIN', 'STRIPED'] as $code) {
            self::placeInTaxon(self::createProduct($code, $code), $shirts);
        }
        self::placeInTaxon(self::createProduct('HIDDEN', 'Hidden', enabled: false), $shirts);
        self::placeInTaxon(self::createProduct('OUTLET_ONLY', 'Outlet only', channels: ['OUTLET']), $shirts);
        $page = self::createPage('Shirts', 'shirts', channels: ['WEB', 'OUTLET']);
        self::openBuilder($client, $page);
        $inTaxon = ['source' => 'products_in_taxon', 'params' => ['taxon' => 'T_SHIRTS', 'includeChildren' => false, 'sort' => 'name']];

        self::assertSame(['count' => 3], self::json($client, 'POST', self::uri($page), $inTaxon));
        self::assertResponseIsSuccessful();
        self::assertSame(['count' => 1], self::json($client, 'POST', self::uri($page, 'OUTLET'), $inTaxon));
    }

    /**
     * @test
     */
    public function it_counts_in_the_preview_s_locale(): void
    {
        $client = self::createClient();
        // Sylius's example factory translates a product into every locale the store has, so the locale comes after
        self::createProduct('BASIC', 'Basic');
        $plain = self::createProduct('PLAIN', 'Plain');
        self::addLocales('WEB', 'de_DE');
        self::translateProduct($plain, 'de_DE', 'Schlicht', 'schlicht');
        $page = self::createPage('New in', 'new-in');
        self::openBuilder($client, $page);

        self::assertSame(['count' => 2], self::json($client, 'POST', self::uri($page), ['source' => 'newest_products', 'params' => []]));
        self::assertSame(['count' => 1], self::json($client, 'POST', self::uri($page, locale: 'de_DE'), ['source' => 'newest_products']));
    }

    /**
     * @test
     */
    public function it_counts_nothing_for_a_source_without_its_required_parameters_or_one_the_code_no_longer_has(): void
    {
        $client = self::createClient();
        self::placeInTaxon(self::createProduct('BASIC', 'Basic'), self::createTaxon('T_SHIRTS', 'T-shirts'));
        $page = self::createPage('Shirts', 'shirts');
        self::openBuilder($client, $page);

        self::assertSame(['count' => 0], self::json($client, 'POST', self::uri($page), ['source' => 'products_in_taxon', 'params' => new \stdClass()]));
        self::assertSame(['count' => 0], self::json($client, 'POST', self::uri($page), ['source' => 'products_in_taxon', 'params' => ['taxon' => 'REMOVED']]));
        self::assertSame(['count' => 0], self::json($client, 'POST', self::uri($page), ['source' => 'retired', 'params' => []]));
    }

    /**
     * @test
     */
    public function it_needs_an_admin_user(): void
    {
        $client = self::createClient();
        $page = self::createPage('Shirts', 'shirts');

        self::json($client, 'POST', self::uri($page), ['source' => 'newest_products', 'params' => []]);

        self::assertResponseRedirects('http://localhost/admin/login');
    }

    /**
     * @test
     */
    public function it_needs_a_valid_preview_token(): void
    {
        $client = self::createClient();
        $page = self::createPage('Shirts', 'shirts');
        self::openBuilder($client, $page);

        $response = self::json($client, 'POST', '/admin/cms/api/sources/count', ['source' => 'newest_products', 'params' => []]);

        self::assertResponseStatusCodeSame(400);
        self::assertSame(['error' => 'The request has no valid preview token'], $response);
    }

    /**
     * @test
     */
    public function it_refuses_a_body_that_is_not_json(): void
    {
        $client = self::createClient();
        $page = self::createPage('Shirts', 'shirts');
        self::openBuilder($client, $page);

        $client->request('POST', self::uri($page), [], [], ['CONTENT_TYPE' => 'application/json'], '{');

        self::assertResponseStatusCodeSame(400);
    }

    /**
     * @test
     *
     * @dataProvider provideBodiesItCannotRead
     */
    public function it_refuses_a_body_it_cannot_read(string $body, string $error): void
    {
        $client = self::createClient();
        $page = self::createPage('Shirts', 'shirts');
        self::openBuilder($client, $page);

        $client->request('POST', self::uri($page), [], [], ['CONTENT_TYPE' => 'application/json'], $body);

        self::assertResponseStatusCodeSame(400);
        self::assertSame(['error' => $error], json_decode((string) $client->getResponse()->getContent(), true));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public function provideBodiesItCannotRead(): iterable
    {
        $error = 'The body needs the code of the source, and its parameters as a map';

        yield 'no source' => ['{"params": {}}', $error];
        yield 'an empty source' => ['{"source": "", "params": {}}', $error];
        yield 'parameters that are a list' => ['{"source": "products_in_taxon", "params": ["T_SHIRTS"]}', $error];
    }

    private static function uri(PageInterface $page, string $channel = 'WEB', string $locale = 'en_US'): string
    {
        return '/admin/cms/api/sources/count?_cms_preview=' . self::previewToken($page->getUuid(), $channel, $locale);
    }
}
