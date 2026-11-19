<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\ScheduledPublishInterface;
use Setono\SyliusCMSPlugin\Targeting\DateWindow;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\DisabledContentTypeKernel;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\StoreLocations;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * The Overview's panels and the Content badge leave out the entries of a content type switched off in
 * setono_sylius_cms.disabled.content_types, store locations here, whose overview pages answer 404, so nothing links to
 * them. See "Overview" in docs/spec/admin.md and "What ships" in docs/spec/collections.md
 */
final class DisabledContentTypeOverviewTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use Entries;
    use StoreLocations;

    /**
     * @test
     */
    public function it_leaves_the_entries_of_a_content_type_switched_off_out_of_the_overviews_panels_and_the_content_badge(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');
        self::mockTime('2026-11-20 08:00:00');
        // Overdue once it's 10:00, an error that needs attention
        self::scheduled(self::createStoreLocation('Aarhus', 'aarhus', 'Aarhus C', published: true), '2026-11-20 09:00:00');
        self::mockTime('2026-11-20 10:00:00');
        // Coming up: a scheduled publish, and a date window that starts
        self::scheduled(self::createStoreLocation('Odense', 'odense', 'Odense C', published: true), '2026-11-20 14:00:00');
        self::withDateWindow(self::createStoreLocation('Aalborg', 'aalborg', 'Aalborg', published: true), '2026-11-21 09:00:00');
        // A draft in progress, never published
        self::createStoreLocation('Vejle', 'vejle', 'Vejle');
        // A page, complete in the channel's one locale
        self::createPage('About us', 'about-us', 'Hello', published: true);

        $crawler = $client->request('GET', '/admin/cms');

        self::assertResponseIsSuccessful();
        self::assertSame(0, $crawler->filter('[data-panel="needs_attention"] [data-entry]')->count());
        self::assertSame(0, $crawler->filter('[data-panel="coming_up"] [data-event]')->count());
        self::assertSame(0, $crawler->filter('[data-panel="drafts_in_progress"] [data-entry]')->count());
        self::assertSame(
            [['en_US', '1 of 1 complete']],
            $crawler->filter('[data-panel="translations"] [data-channel="WEB"] [data-locale]')->each(static fn (Crawler $locale): array => [(string) $locale->attr('data-locale'), $locale->filter('td')->eq(1)->text()]),
        );
        self::assertNull($crawler->filter('a[data-cms-problem-count]')->getNode(0), 'The Content badge counts no problem');
    }

    private static function scheduled(EntryInterface $entry, string $at): void
    {
        /** @var FactoryInterface<ScheduledPublishInterface> $factory */
        $factory = self::getContainer()->get('setono_sylius_cms.factory.scheduled_publish');
        $scheduledPublish = $factory->createNew();
        $scheduledPublish->setEntry($entry);
        $scheduledPublish->schedule(new \DateTimeImmutable($at), 'sylius');

        self::manager()->persist($scheduledPublish);
        self::manager()->flush();
    }

    private static function withDateWindow(EntryInterface $entry, string $from): void
    {
        $entry->setDateWindow(new DateWindow(new \DateTimeImmutable($from), null));
        self::manager()->flush();
    }

    private static function manager(): ObjectManager
    {
        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('doctrine.orm.entity_manager');

        return $manager;
    }

    /**
     * @param array<array-key, mixed> $options
     */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new DisabledContentTypeKernel('test', (bool) ($_SERVER['APP_DEBUG'] ?? true));
    }
}
