<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Event\EntryCreated;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Targeting\Audience;
use Setono\SyliusCMSPlugin\Targeting\DateWindow;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Sylius\Bundle\CoreBundle\Fixture\Factory\ExampleFactoryInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class CreateEntryTest extends WebTestCase
{
    use Entries;

    /**
     * @test
     */
    public function it_needs_an_admin_user(): void
    {
        $client = self::createClient();

        $client->request('GET', '/admin/cms/entries/page/new');

        self::assertResponseRedirects('http://localhost/admin/login');
    }

    /**
     * @test
     */
    public function it_creates_a_page_from_a_title_and_a_slug_and_opens_the_builder(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');

        $client->request('GET', '/admin/cms/entries/page/new');
        self::assertResponseIsSuccessful();

        $client->submitForm('Create', [
            'setono_sylius_cms_create_entry[title]' => 'About us',
            'setono_sylius_cms_create_entry[slug]' => 'about-us',
        ]);

        /** @var RepositoryInterface<PageInterface> $repository */
        $repository = self::getContainer()->get('setono_sylius_cms.repository.page');
        $pages = $repository->findAll();
        self::assertCount(1, $pages);

        $page = $pages[0];
        self::assertSame('About us', $page->getTranslation('en_US')->getTitle());
        self::assertSame('about-us', $page->getTranslation('en_US')->getSlug());
        self::assertFalse($page->isPublished());
        self::assertResponseRedirects(sprintf('/admin/cms/entry/%s/builder', $page->getUuid()));
    }

    /**
     * @test
     */
    public function a_new_entry_is_enabled_for_everyone_in_every_channel(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');
        self::createChannel('B2B');

        $client->request('GET', '/admin/cms/entries/page/new');
        $client->submitForm('Create', [
            'setono_sylius_cms_create_entry[title]' => 'About us',
            'setono_sylius_cms_create_entry[slug]' => 'about-us',
        ]);

        /** @var RepositoryInterface<PageInterface> $repository */
        $repository = self::getContainer()->get('setono_sylius_cms.repository.page');
        $page = $repository->findOneBy([]);
        self::assertInstanceOf(PageInterface::class, $page);
        self::assertSame(['B2B', 'WEB'], $page->getChannelCodes());
        self::assertTrue($page->isEnabled());
        self::assertTrue($page->getAudience()->equals(Audience::everyone()));
        self::assertTrue($page->getDateWindow()->equals(new DateWindow()));
    }

    /**
     * @test
     */
    public function it_dispatches_entry_created_after_the_entry_is_created(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $client->loginUser(self::admin(), 'admin');
        $client->request('GET', '/admin/cms/entries/page/new');

        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $outside = $connection->getTransactionNestingLevel();

        /** @var list<array{EntryCreated, int, mixed}> $dispatched the event, the transaction nesting level, and the stored entry's content type */
        $dispatched = [];
        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = self::getContainer()->get('event_dispatcher');
        $eventDispatcher->addListener(EntryCreated::class, static function (EntryCreated $event) use (&$dispatched, $connection): void {
            $dispatched[] = [$event, $connection->getTransactionNestingLevel(), $connection->fetchOne('SELECT content_type FROM setono_sylius_cms__entry WHERE uuid = ?', [$event->uuid])];
        });

        $client->submitForm('Create', [
            'setono_sylius_cms_create_entry[title]' => 'About us',
            'setono_sylius_cms_create_entry[slug]' => 'about-us',
        ]);

        /** @var RepositoryInterface<PageInterface> $repository */
        $repository = self::getContainer()->get('setono_sylius_cms.repository.page');
        $page = $repository->findOneBy([]);
        self::assertInstanceOf(PageInterface::class, $page);

        self::assertCount(1, $dispatched);
        [[$event, $level, $stored]] = $dispatched;
        self::assertSame($page->getUuid(), $event->uuid);
        self::assertSame('page', $event->contentType);
        self::assertSame($outside, $level);
        self::assertSame('page', $stored);
    }

    /**
     * @test
     */
    public function it_refuses_an_empty_title_and_an_invalid_slug(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');

        $client->request('GET', '/admin/cms/entries/page/new');
        $client->submitForm('Create', [
            'setono_sylius_cms_create_entry[title]' => '',
            'setono_sylius_cms_create_entry[slug]' => '/about//us/',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorCount(2, '.sylius-validation-error');
    }

    /**
     * @test
     */
    public function it_answers_404_for_a_content_type_it_does_not_have(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');

        $client->request('GET', '/admin/cms/entries/recipe/new');

        self::assertResponseStatusCodeSame(404);
    }

    private static function createChannel(string $code): void
    {
        /** @var ExampleFactoryInterface $factory */
        $factory = self::getContainer()->get('sylius.fixture.example_factory.channel');
        $channel = $factory->create([
            'code' => $code,
            'name' => $code,
            'hostname' => strtolower($code) . '.localhost',
            'locales' => ['en_US'],
            'default_locale' => 'en_US',
            'currencies' => ['USD'],
            'base_currency' => 'USD',
            'menu_taxon' => null,
        ]);

        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('sylius.manager.channel');
        $manager->persist($channel);
        $manager->flush();
    }
}
