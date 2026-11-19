<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Application\Entity\StoreLocation;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\StoreLocations;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * A content type's own Snippet field, the test application's store locations' banner, which is a column of the entry
 * rather than a value in its tree, is found as a block's Snippet field is: the entries referring to a snippet hold it
 * there, and the snippet's overview page lists them under Used in. See "Content types" in docs/spec/extending.md
 */
final class OwnSnippetFieldTest extends WebTestCase
{
    use Entries;
    use StoreLocations;

    /**
     * @test
     */
    public function it_finds_an_entry_whose_own_snippet_column_holds_the_snippet_among_those_referring_to_it(): void
    {
        self::bootKernel();
        $hours = self::createSnippet('Opening hours', published: true);
        $aarhus = self::withBanner(self::createStoreLocation('Aarhus', 'aarhus', 'Aarhus C', published: true), $hours->getUuid());
        self::createStoreLocation('Odense', 'odense', 'Odense C', published: true);

        /** @var EntryRepositoryInterface $entries */
        $entries = self::getContainer()->get(EntryRepositoryInterface::class);

        self::assertSame([$aarhus->getUuid()], array_map(static fn ($entry): string => $entry->getUuid(), $entries->findReferencing([$hours->getUuid()])));
        self::assertSame([$aarhus->getUuid()], array_map(static fn ($entry): string => $entry->getUuid(), $entries->findReferencing([strtoupper($hours->getUuid())])), 'A UUID matches whatever its case');
    }

    /**
     * @test
     */
    public function it_lists_an_entry_whose_own_snippet_field_shows_the_snippet_under_used_in(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');
        $hours = self::createSnippet('Opening hours', published: true);
        self::withBanner(self::createStoreLocation('Aarhus', 'aarhus', 'Aarhus C', published: true), $hours->getUuid());

        $crawler = $client->request('GET', '/admin/cms/entry/' . $hours->getUuid());

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Aarhus', $crawler->filter('[data-section="used_in"]')->text());
    }

    private static function withBanner(StoreLocation $storeLocation, string $snippet): StoreLocation
    {
        $storeLocation->setBanner($snippet);

        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('app.manager.store_location');
        $manager->flush();

        return $storeLocation;
    }
}
