<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Event\EntrySettingsChanged;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\StoreLocations;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Editors drag the entries of a content type ordered by position into order in its list, which takes effect on save.
 * The test application's announcements are ordered by position. See "Entry lists" in docs/spec/admin.md and "What takes
 * effect on save" in docs/spec/publishing.md
 */
final class EntryOrderTest extends WebTestCase
{
    use Entries;
    use StoreLocations;

    /**
     * @test
     */
    public function it_puts_a_new_entry_of_a_content_type_ordered_by_position_last_and_lists_the_entries_in_their_order(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');
        $first = self::createAnnouncement('Free shipping');
        $second = self::createAnnouncement('Summer sale');
        $third = self::createAnnouncement('New store');

        self::assertSame([0, 1, 2], [$first->getPosition(), $second->getPosition(), $third->getPosition()]);
        self::assertSame(['Free shipping', 'Summer sale', 'New store'], self::listed($client->request('GET', '/admin/cms/entries/announcement')));
        self::assertSelectorExists('[data-cms-entry-order][data-url="/admin/cms/entries/announcement/order"]');
    }

    /**
     * @test
     */
    public function it_saves_the_order_editors_drag_the_entries_into_and_dispatches_entry_settings_changed_with_the_position(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');
        $first = self::createAnnouncement('Free shipping');
        $second = self::createAnnouncement('Summer sale');
        $third = self::createAnnouncement('New store');
        $csrfToken = self::csrfToken($client->request('GET', '/admin/cms/entries/announcement'));
        // So the listener stays on the event dispatcher the next request runs with
        $client->disableReboot();

        /** @var list<EntrySettingsChanged> $dispatched */
        $dispatched = [];
        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = self::getContainer()->get('event_dispatcher');
        $eventDispatcher->addListener(EntrySettingsChanged::class, static function (EntrySettingsChanged $event) use (&$dispatched): void {
            $dispatched[] = $event;
        });

        $client->request('POST', '/admin/cms/entries/announcement/order', ['_csrf_token' => $csrfToken, 'entries' => [$third->getUuid(), $first->getUuid(), $second->getUuid()]]);

        self::assertResponseStatusCodeSame(204);
        self::assertSame([1, 2, 0], array_map(static fn (EntryInterface $entry): ?int => self::reload($entry)->getPosition(), [$first, $second, $third]));
        self::assertEqualsCanonicalizing(
            [[$first->getUuid(), ['position']], [$second->getUuid(), ['position']], [$third->getUuid(), ['position']]],
            array_map(static fn (EntrySettingsChanged $event): array => [$event->uuid, $event->changed], $dispatched),
        );
        self::assertSame(['New store', 'Free shipping', 'Summer sale'], self::listed($client->request('GET', '/admin/cms/entries/announcement')));
    }

    /**
     * @test
     */
    public function it_refuses_an_order_without_the_lists_csrf_token(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');
        $announcement = self::createAnnouncement('Free shipping');

        $client->request('POST', '/admin/cms/entries/announcement/order', ['_csrf_token' => 'forged', 'entries' => [$announcement->getUuid()]]);

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * @test
     */
    public function it_refuses_to_order_the_entries_of_a_content_type_ordered_otherwise(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');
        $storeLocation = self::createStoreLocation('Aarhus', 'aarhus');
        $csrfToken = self::csrfToken($client->request('GET', '/admin/cms/entries/announcement'));

        $client->request('GET', '/admin/cms/entries/store_location');
        self::assertSelectorNotExists('[data-cms-entry-order]');

        $client->request('POST', '/admin/cms/entries/store_location/order', ['_csrf_token' => $csrfToken, 'entries' => [$storeLocation->getUuid()]]);

        self::assertResponseStatusCodeSame(400);
        self::assertNull(self::reload($storeLocation)->getPosition());
    }

    /**
     * @return list<string> the titles of the list's rows, in order
     */
    private static function listed(Crawler $crawler): array
    {
        /** @var list<string> $titles */
        $titles = $crawler->filter('[data-column="title"] strong')->each(static fn (Crawler $title): string => trim($title->text()));

        return $titles;
    }

    private static function csrfToken(Crawler $crawler): string
    {
        return (string) $crawler->filter('[data-cms-entry-order]')->attr('data-csrf-token');
    }

    private static function reload(EntryInterface $entry): EntryInterface
    {
        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('doctrine.orm.entity_manager');
        $manager->clear();

        /** @var EntryRepositoryInterface $repository */
        $repository = self::getContainer()->get('setono_sylius_cms.repository.entry');
        $reloaded = $repository->findOneByUuid($entry->getUuid());
        self::assertInstanceOf(EntryInterface::class, $reloaded);

        return $reloaded;
    }
}
