<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin;

use Doctrine\DBAL\Connection;
use Setono\SyliusCMSPlugin\Event\EntryCreated;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Targeting\Audience;
use Setono\SyliusCMSPlugin\Targeting\DateWindow;
use Setono\SyliusCMSPlugin\Tests\Application\Entity\Announcement;
use Setono\SyliusCMSPlugin\Tests\Support\Admins;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * The create dialog's end on the server. See "Create dialog" in docs/spec/admin.md
 */
final class CreateEntryTest extends WebTestCase
{
    use Admins;
    use Entries;

    /**
     * @test
     */
    public function it_needs_an_admin_user(): void
    {
        $client = self::createClient();

        $client->request('GET', '/admin/cms/entries/page/new');
        self::assertResponseRedirects('http://localhost/admin/login');

        $client->request('GET', '/admin/cms/api/slug?title=About+us');
        self::assertResponseRedirects('http://localhost/admin/login');
    }

    /**
     * @test
     */
    public function it_creates_a_never_published_page_in_every_channel_and_opens_the_builder(): void
    {
        $client = self::signedInClient();
        self::createChannel('B2B', 'Wholesale');

        $crawler = $client->request('GET', '/admin/cms/entries/page/new');
        self::assertResponseIsSuccessful();
        // Every channel is picked to start with, the channels by name
        self::assertSame(['WEB', 'B2B'], $crawler->filter('input[name="setono_sylius_cms_create_entry[channels][]"]:checked')->each(static fn (Crawler $box): string => (string) $box->attr('value')));

        $client->submitForm('Create', [
            'setono_sylius_cms_create_entry[title]' => 'About us',
            'setono_sylius_cms_create_entry[slug]' => 'about-us',
        ]);

        $page = self::onlyPage();
        self::assertSame('About us', $page->getTranslation('en_US')->getTitle());
        self::assertSame('about-us', $page->getTranslation('en_US')->getSlug());
        self::assertFalse($page->isPublished());
        self::assertSame(['B2B', 'WEB'], $page->getChannelCodes());
        self::assertTrue($page->isEnabled());
        self::assertTrue($page->getAudience()->equals(Audience::everyone()));
        self::assertTrue($page->getDateWindow()->equals(new DateWindow()));
        self::assertSame('John Doe', $page->getLastEditedBy());
        // The builder's back button goes to the new page's overview page
        self::assertResponseRedirects(sprintf('/admin/cms/entry/%s/builder', $page->getUuid()));
    }

    /**
     * @test
     */
    public function it_puts_the_page_in_the_layout_the_editor_picked_from_the_wireframe_cards_with_the_default_picked_to_start_with(): void
    {
        $client = self::signedInClient();

        $crawler = $client->request('GET', '/admin/cms/entries/page/new');
        $cards = $crawler->filter('fieldset[data-cms-create-entry-layouts] .cms-layout-card');
        self::assertSame('Layout', $crawler->filter('fieldset[data-cms-create-entry-layouts] legend')->text());
        self::assertSame(['Standard', 'Landing'], $cards->each(static fn (Crawler $card): string => trim($card->text())));
        self::assertSame('/bundles/setonosyliuscmsplugin/thumbnail/layout/page/standard.svg', $cards->eq(0)->filter('img')->attr('src'));
        self::assertCount(0, $cards->eq(1)->filter('img'), 'A layout without a thumbnail has a plain card');
        self::assertSame(['standard'], $crawler->filter('input[name="setono_sylius_cms_create_entry[layout]"]:checked')->each(static fn (Crawler $radio): string => (string) $radio->attr('value')));

        $client->submitForm('Create', [
            'setono_sylius_cms_create_entry[title]' => 'Summer sale',
            'setono_sylius_cms_create_entry[slug]' => 'summer-sale',
            'setono_sylius_cms_create_entry[layout]' => 'landing',
        ]);

        self::assertEquals(new StoredLayout('landing', 1), self::onlyPage()->getBlockTree()->layout);
    }

    /**
     * @test
     */
    public function it_puts_a_page_in_the_default_layout_unless_the_editor_picks_another(): void
    {
        $client = self::signedInClient();

        $client->request('GET', '/admin/cms/entries/page/new');
        $client->submitForm('Create', [
            'setono_sylius_cms_create_entry[title]' => 'About us',
            'setono_sylius_cms_create_entry[slug]' => 'about-us',
        ]);

        self::assertEquals(new StoredLayout('standard', 1), self::onlyPage()->getBlockTree()->layout);
    }

    /**
     * @test
     */
    public function it_puts_the_entry_in_the_channels_the_editor_picked(): void
    {
        $client = self::signedInClient();
        self::createChannel('B2B', 'Wholesale');

        $client->request('GET', '/admin/cms/entries/page/new');
        $form = $client->getCrawler()->selectButton('Create')->form();
        $form['setono_sylius_cms_create_entry[title]'] = 'Wholesale terms';
        $form['setono_sylius_cms_create_entry[slug]'] = 'wholesale-terms';
        /** @var \Symfony\Component\DomCrawler\Field\ChoiceFormField[] $channels */
        $channels = $form['setono_sylius_cms_create_entry[channels]'];
        foreach ($channels as $channel) {
            if ('WEB' === $channel->availableOptionValues()[0]) {
                $channel->untick();
            }
        }
        $client->submit($form);

        self::assertSame(['B2B'], self::onlyPage()->getChannelCodes());
    }

    /**
     * @test
     */
    public function it_has_no_channels_to_pick_in_a_store_with_one_channel(): void
    {
        $client = self::signedInClient();

        $client->request('GET', '/admin/cms/entries/page/new');

        self::assertSelectorNotExists('[name^="setono_sylius_cms_create_entry[channels]"]');
    }

    /**
     * @test
     */
    public function it_fills_an_empty_slug_from_the_title(): void
    {
        $client = self::signedInClient();

        $client->request('GET', '/admin/cms/entries/page/new');
        $client->submitForm('Create', [
            'setono_sylius_cms_create_entry[title]' => 'Über uns',
            'setono_sylius_cms_create_entry[slug]' => '',
        ]);

        self::assertSame('uber-uns', self::onlyPage()->getTranslation('en_US')->getSlug());
    }

    /**
     * @test
     */
    public function it_generates_the_slug_of_a_title_for_the_dialog_with_syliuss_slug_generator(): void
    {
        $client = self::signedInClient();

        $client->request('GET', '/admin/cms/api/slug?title=' . rawurlencode("Shopper's guide to Über"));

        self::assertResponseIsSuccessful();
        self::assertSame(['slug' => 'shopper-s-guide-to-uber'], json_decode((string) $client->getResponse()->getContent(), true));
    }

    /**
     * @test
     */
    public function it_creates_an_entry_of_a_content_type_without_its_own_url_without_a_slug(): void
    {
        $client = self::signedInClient();

        $client->request('GET', '/admin/cms/entries/announcement/new');
        self::assertSelectorNotExists('[name="setono_sylius_cms_create_entry[slug]"]');
        $client->submitForm('Create', ['setono_sylius_cms_create_entry[title]' => 'Free shipping']);

        /** @var RepositoryInterface<Announcement> $repository */
        $repository = self::getContainer()->get('app.repository.announcement');
        $announcement = $repository->findOneBy([]);
        self::assertInstanceOf(Announcement::class, $announcement);
        self::assertSame('Free shipping', $announcement->getTranslation('en_US')->getTitle());
        self::assertNull($announcement->getTranslation('en_US')->getSlug());
        self::assertFalse($announcement->isPublished());
    }

    /**
     * @test
     */
    public function it_answers_the_dialog_its_form_alone_which_posts_to_the_content_types_create_url(): void
    {
        $client = self::signedInClient();

        $crawler = $client->xmlHttpRequest('GET', '/admin/cms/entries/page/new');

        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('<html', (string) $client->getResponse()->getContent());
        self::assertSame('New Page', trim($crawler->filter('[data-cms-create-entry-heading]')->text()));
        $form = $crawler->filter('form[data-cms-create-entry-form]');
        self::assertSame('/admin/cms/entries/page/new', $form->attr('action'));
        self::assertSame('post', strtolower((string) $form->attr('method')));
        self::assertCount(1, $form->filter('input[name="setono_sylius_cms_create_entry[title]"][data-cms-create-entry-title][required][maxlength="255"]'));
        self::assertSame('/admin/cms/api/slug', $form->filter('input[name="setono_sylius_cms_create_entry[slug]"]')->attr('data-cms-create-entry-slug'));
        self::assertSame('(?!/)(?!.*//)(?!.*/$).+', $form->filter('input[name="setono_sylius_cms_create_entry[slug]"]')->attr('pattern'));
    }

    /**
     * @test
     */
    public function it_puts_the_title_and_slug_in_the_admin_users_locale_when_the_entrys_channels_have_it(): void
    {
        $client = self::createClient();
        self::addLocales('WEB', 'da_DK');
        $client->loginUser(self::createAdmin('jensen', 'Jens', 'Jensen', 'da_DK'), 'admin');

        $client->request('GET', '/admin/cms/entries/page/new');
        $client->submitForm('Create', [
            'setono_sylius_cms_create_entry[title]' => 'Om os',
            'setono_sylius_cms_create_entry[slug]' => 'om-os',
        ]);

        $page = self::onlyPage();
        self::assertSame(['da_DK'], $page->getTranslations()->getKeys());
        self::assertSame('Om os', $page->getTranslation('da_DK')->getTitle());

        // The builder opens in that locale
        $crawler = $client->request('GET', sprintf('/admin/cms/entry/%s/builder', $page->getUuid()));
        /** @var array{locale: string} $config */
        $config = json_decode((string) $crawler->filter('#cms-builder')->attr('data-config'), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('da_DK', $config['locale']);
    }

    /**
     * @test
     */
    public function it_puts_the_title_and_slug_in_the_channels_default_locale_when_the_admin_users_is_not_one_of_its_locales(): void
    {
        $client = self::createClient();
        $client->loginUser(self::createAdmin('jensen', 'Jens', 'Jensen', 'da_DK'), 'admin');

        $client->request('GET', '/admin/cms/entries/page/new');
        $client->submitForm('Create', [
            'setono_sylius_cms_create_entry[title]' => 'About us',
            'setono_sylius_cms_create_entry[slug]' => 'about-us',
        ]);

        self::assertSame(['en_US'], self::onlyPage()->getTranslations()->getKeys());
    }

    /**
     * @test
     */
    public function it_starts_an_entry_made_by_its_resource_factory_in_every_channel_too(): void
    {
        self::bootKernel();
        self::createChannel('B2B');

        /** @var FactoryInterface<PageInterface> $factory */
        $factory = self::getContainer()->get('setono_sylius_cms.factory.page');

        self::assertSame(['B2B', 'WEB'], $factory->createNew()->getChannelCodes());
    }

    /**
     * @test
     */
    public function it_dispatches_entry_created_after_the_entry_is_created(): void
    {
        $client = self::signedInClient();
        $client->disableReboot();
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

        $page = self::onlyPage();
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
    public function it_shows_the_form_with_its_errors_as_a_page_when_it_refuses_a_submit(): void
    {
        $client = self::signedInClient();

        $client->request('GET', '/admin/cms/entries/page/new');
        $client->submitForm('Create', [
            'setono_sylius_cms_create_entry[title]' => '',
            'setono_sylius_cms_create_entry[slug]' => '/about//us/',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('h1', 'New Page');
        self::assertSelectorCount(2, '.sylius-validation-error');
    }

    /**
     * @test
     */
    public function it_answers_404_for_a_content_type_it_does_not_have(): void
    {
        $client = self::signedInClient();

        $client->request('GET', '/admin/cms/entries/recipe/new');

        self::assertResponseStatusCodeSame(404);
    }

    private static function signedInClient(): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');

        return $client;
    }

    private static function onlyPage(): PageInterface
    {
        /** @var RepositoryInterface<PageInterface> $repository */
        $repository = self::getContainer()->get('setono_sylius_cms.repository.page');
        $pages = $repository->findAll();
        self::assertCount(1, $pages);
        $page = reset($pages);
        self::assertInstanceOf(PageInterface::class, $page);

        return $page;
    }
}
