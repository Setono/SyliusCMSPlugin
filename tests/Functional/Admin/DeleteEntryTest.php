<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin;

use Doctrine\DBAL\Connection;
use Setono\SyliusCMSPlugin\Event\BeforeEntryDelete;
use Setono\SyliusCMSPlugin\Event\EntryDeleted;
use Setono\SyliusCMSPlugin\Event\PlacementsChanged;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyManagerInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\Placements;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class DeleteEntryTest extends WebTestCase
{
    use Entries;
    use Placements;

    /**
     * @test
     */
    public function it_deletes_an_entry_with_its_draft_and_goes_back_to_the_list(): void
    {
        $client = self::signedInClient();
        $page = self::createPage('About us', 'about-us', 'Hello', published: true);
        $uuid = $page->getUuid();
        self::saveDraft($page);
        self::createPage('Contact', 'contact');

        $client->request('GET', '/admin/cms/entries/page?criteria%5Bsearch%5D=about');
        $client->submit(self::row($client, $uuid)->filter('form')->form());

        self::assertResponseRedirects('http://localhost/admin/cms/entries/page?criteria%5Bsearch%5D=about');
        self::assertNull(self::pages()->findOneBy(['uuid' => $uuid]));
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        self::assertEquals(0, $connection->fetchOne('SELECT COUNT(*) FROM setono_sylius_cms__draft'));

        $client->followRedirect();
        self::assertSelectorTextContains('.sylius-flash-message', 'The entry has been deleted');
        self::assertSelectorNotExists(sprintf('[data-entry="%s"]', $uuid));
    }

    /**
     * @test
     */
    public function it_dispatches_entry_deleted_after_the_entry_is_deleted(): void
    {
        $client = self::signedInClient();
        $client->disableReboot();
        $page = self::createPage('About us', 'about-us');
        $uuid = $page->getUuid();
        $client->request('GET', '/admin/cms/entries/page');

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $outside = $connection->getTransactionNestingLevel();

        /** @var list<array{EntryDeleted, int, mixed}> $dispatched the event, the transaction nesting level, and how many entries have its UUID */
        $dispatched = [];
        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = self::getContainer()->get('event_dispatcher');
        $eventDispatcher->addListener(EntryDeleted::class, static function (EntryDeleted $event) use (&$dispatched, $connection): void {
            $dispatched[] = [$event, $connection->getTransactionNestingLevel(), $connection->fetchOne('SELECT COUNT(*) FROM setono_sylius_cms__entry WHERE uuid = ?', [$event->uuid])];
        });

        $client->submit(self::row($client, $uuid)->filter('form')->form());

        self::assertCount(1, $dispatched);
        [[$event, $level, $count]] = $dispatched;
        self::assertSame($uuid, $event->uuid);
        self::assertSame('page', $event->contentType);
        self::assertSame($outside, $level);
        self::assertEquals(0, $count);
    }

    /**
     * @test
     */
    public function it_dispatches_placements_changed_with_the_slots_a_deleted_snippet_was_placed_in(): void
    {
        $client = self::signedInClient();
        $client->disableReboot();
        $snippet = self::createSnippet('Free shipping');
        $uuid = $snippet->getUuid();
        self::place($snippet, 'shop.footer');
        self::place($snippet, 'shop.top');
        self::place($snippet, 'shop.footer');
        self::place(self::createSnippet('Opening hours'), 'shop.before_content');
        $client->request('GET', '/admin/cms/entries/snippet');

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $outside = $connection->getTransactionNestingLevel();

        /** @var list<array{object, int}> $dispatched the event and the transaction nesting level */
        $dispatched = [];
        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = self::getContainer()->get('event_dispatcher');
        foreach ([EntryDeleted::class, PlacementsChanged::class] as $class) {
            $eventDispatcher->addListener($class, static function (object $event) use (&$dispatched, $connection): void {
                $dispatched[] = [$event, $connection->getTransactionNestingLevel()];
            });
        }

        $client->submit(self::row($client, $uuid)->filter('form')->form());

        self::assertCount(2, $dispatched);
        [[$deleted], [$placementsChanged, $level]] = $dispatched;
        self::assertInstanceOf(EntryDeleted::class, $deleted);
        self::assertInstanceOf(PlacementsChanged::class, $placementsChanged);
        self::assertSame([$uuid], $placementsChanged->snippetUuids);
        self::assertSame(['shop.footer', 'shop.top'], $placementsChanged->slots);
        self::assertSame($outside, $level);
        self::assertEquals(0, $connection->fetchOne('SELECT COUNT(*) FROM setono_sylius_cms__placement WHERE slot IN (?, ?)', ['shop.footer', 'shop.top']));
    }

    /**
     * @test
     */
    public function it_dispatches_no_placements_changed_for_a_snippet_that_was_placed_nowhere(): void
    {
        $client = self::signedInClient();
        $client->disableReboot();
        $snippet = self::createSnippet('Free shipping');
        $client->request('GET', '/admin/cms/entries/snippet');

        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = self::getContainer()->get('event_dispatcher');
        $eventDispatcher->addListener(PlacementsChanged::class, static fn () => self::fail('Deleting a snippet placed nowhere dispatched PlacementsChanged'));

        $client->submit(self::row($client, $snippet->getUuid())->filter('form')->form());

        self::assertResponseRedirects();
    }

    /**
     * @test
     */
    public function it_keeps_an_entry_whose_delete_a_listener_vetoes_and_shows_why(): void
    {
        $client = self::signedInClient();
        $client->disableReboot();
        $page = self::createPage('About us', 'about-us');
        $uuid = $page->getUuid();
        $client->request('GET', '/admin/cms/entries/page');

        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = self::getContainer()->get('event_dispatcher');
        $eventDispatcher->addListener(BeforeEntryDelete::class, static fn (BeforeEntryDelete $event) => $event->veto('The about page stays'));
        $eventDispatcher->addListener(EntryDeleted::class, static fn () => self::fail('A vetoed delete dispatched EntryDeleted'));

        $client->submit(self::row($client, $uuid)->filter('form')->form());

        self::assertResponseRedirects('http://localhost/admin/cms/entries/page');
        self::assertNotNull(self::pages()->findOneBy(['uuid' => $uuid]));

        $client->followRedirect();
        self::assertSelectorTextContains('.sylius-flash-message.negative', 'The about page stays');
        self::assertSelectorExists(sprintf('[data-entry="%s"]', $uuid));
    }

    /**
     * @test
     */
    public function it_refuses_a_delete_without_the_csrf_token_of_the_entry(): void
    {
        $client = self::signedInClient();
        $page = self::createPage('About us', 'about-us');
        $other = self::createPage('Contact', 'contact');
        $client->request('GET', '/admin/cms/entries/page');
        $token = (string) self::row($client, $other->getUuid())->filter('input[name="_csrf_token"]')->attr('value');

        $client->request('DELETE', '/admin/cms/entry/' . $page->getUuid(), ['_csrf_token' => $token]);

        self::assertResponseStatusCodeSame(403);
        self::assertNotNull(self::pages()->findOneBy(['uuid' => $page->getUuid()]));
    }

    /**
     * @test
     */
    public function it_goes_back_to_the_content_types_list_when_it_was_not_sent_from_one_of_its_own_pages(): void
    {
        $client = self::signedInClient();
        $page = self::createPage('About us', 'about-us');
        $client->request('GET', '/admin/cms/entries/page');
        $form = self::row($client, $page->getUuid())->filter('form')->form();

        $client->request('DELETE', '/admin/cms/entry/' . $page->getUuid(), $form->getValues(), [], ['HTTP_REFERER' => 'https://example.com/admin/cms/entries']);

        self::assertResponseRedirects('/admin/cms/entries/page');
    }

    /**
     * @test
     */
    public function it_needs_an_admin_user_and_an_entry(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about-us');

        $client->request('DELETE', '/admin/cms/entry/' . $page->getUuid());
        self::assertResponseRedirects('http://localhost/admin/login');

        $client->loginUser(self::admin(), 'admin');
        $client->request('DELETE', '/admin/cms/entry/0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e');
        self::assertResponseStatusCodeSame(404);
    }

    private static function signedInClient(): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');

        return $client;
    }

    /**
     * The list's row of the entry, whose title cell names it
     */
    private static function row(KernelBrowser $client, string $uuid): Crawler
    {
        $row = $client->getCrawler()->filterXPath(sprintf('//table/tbody/tr[.//*[@data-entry="%s"]]', $uuid));
        self::assertCount(1, $row);

        return $row;
    }

    private static function saveDraft(PageInterface $page): void
    {
        /** @var WorkingCopyManagerInterface $workingCopies */
        $workingCopies = self::getContainer()->get(WorkingCopyManagerInterface::class);
        $workingCopy = $workingCopies->getWorkingCopy($page);
        $workingCopy->setTitle('About our shop');
        $workingCopies->save($page, $workingCopy);
    }

    /**
     * @return RepositoryInterface<PageInterface>
     */
    private static function pages(): RepositoryInterface
    {
        /** @var RepositoryInterface<PageInterface> $repository */
        $repository = self::getContainer()->get('setono_sylius_cms.repository.page');

        return $repository;
    }
}
