<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\StoreLocations;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * A project's own content type with a URL of its own: the test application's store locations, under "stores", in their
 * layout, which prints their fields. See "Content types" in docs/spec/extending.md
 */
final class StoreLocationsTest extends WebTestCase
{
    use Entries;
    use StoreLocations;

    /**
     * @test
     */
    public function it_renders_a_published_store_location_at_its_url_under_its_prefix_with_its_fields(): void
    {
        $client = self::createClient();
        self::createStoreLocation('Aarhus', 'aarhus', 'Aarhus C', 'Turn left at the fountain', published: true);

        $client->request('GET', '/en_US/stores/aarhus');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('.app-store h1', 'Aarhus');
        self::assertSelectorTextSame('.app-store__city', 'Aarhus C');
        self::assertSelectorTextSame('.app-store__directions', 'Turn left at the fountain');
    }

    /**
     * @test
     */
    public function it_answers_404_for_a_store_location_outside_its_prefix(): void
    {
        $client = self::createClient();
        self::createStoreLocation('Aarhus', 'aarhus', 'Aarhus C', published: true);

        $client->request('GET', '/en_US/aarhus');

        self::assertResponseStatusCodeSame(404);
    }
}
