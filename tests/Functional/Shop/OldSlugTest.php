<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\OldSlugInterface;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Repository\OldSlugRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * A publish that changes a slug keeps the old one, whose URL redirects to the current one while no live entry claims
 * it. See "Old slugs" in docs/spec/urls-and-seo.md
 */
final class OldSlugTest extends WebTestCase
{
    use Entries;

    /**
     * @test
     */
    public function a_publish_that_changes_a_slug_keeps_the_old_one(): void
    {
        self::createClient();
        $page = self::createPage('About us', 'about', 'Hello', published: true);

        self::renameAndPublish($page, 'about-us');
        self::renameAndPublish($page, 'about-our-shop');

        self::assertSame([['en_US', 'about-us'], ['en_US', 'about']], self::oldSlugsOf($page));
    }

    /**
     * @test
     */
    public function an_old_slug_answers_301_to_the_current_url_keeping_the_query_string(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about', 'Hello', published: true);
        self::renameAndPublish($page, 'about-us');

        $client->request('GET', '/en_US/about?utm_source=newsletter');

        self::assertResponseRedirects('/en_US/about-us?utm_source=newsletter', 301);
        $client->followRedirect();
        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextSame('.cms-heading', 'Hello');
    }

    /**
     * @test
     */
    public function an_old_slug_stops_redirecting_once_another_entry_is_published_at_its_url(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about', 'Hello', published: true);
        self::renameAndPublish($page, 'about-us');
        // Not live yet, so the old slug still redirects
        $other = self::createPage('About', 'about', 'The new about page');

        $client->request('GET', '/en_US/about');
        self::assertResponseRedirects('/en_US/about-us', 301);

        self::publishEntry($other);
        $client->request('GET', '/en_US/about');

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextSame('.cms-heading', 'The new about page');
    }

    /**
     * @test
     */
    public function an_unavailable_entrys_old_slug_answers_404(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about', 'Hello', published: true);
        self::renameAndPublish($page, 'about-us');
        $page = self::reloadPage($page);
        $page->setEnabled(false);
        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.page');
        $manager->flush();

        $client->request('GET', '/en_US/about');

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @test
     */
    public function an_old_slug_redirects_only_in_its_own_locale_and_the_entrys_channels(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about', 'Hello', published: true);
        self::renameAndPublish($page, 'about-us');
        $elsewhere = self::createPage('Elsewhere', 'elsewhere', 'Hello', published: true, channels: ['OTHER']);
        self::renameAndPublish($elsewhere, 'somewhere-else');

        $client->request('GET', '/de_DE/about');
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', '/en_US/elsewhere');
        self::assertResponseStatusCodeSame(404);
        self::assertFalse($client->getRequest()->attributes->has('_route'));
    }

    /**
     * @test
     */
    public function going_back_to_an_old_slug_makes_it_current_again(): void
    {
        $client = self::createClient();
        $page = self::createPage('About us', 'about', 'Hello', published: true);
        self::renameAndPublish($page, 'about-us');
        self::renameAndPublish($page, 'about');

        self::assertSame([['en_US', 'about-us']], self::oldSlugsOf($page));

        $client->request('GET', '/en_US/about');
        self::assertResponseStatusCodeSame(200);

        $client->request('GET', '/en_US/about-us');
        self::assertResponseRedirects('/en_US/about', 301);
    }

    private static function renameAndPublish(EntryInterface $entry, string $slug): void
    {
        self::saveChanges($entry, [['op' => 'set_entry_value', 'field' => 'slug', 'locale' => 'en_US', 'value' => $slug]]);
        self::publishEntry($entry);
    }

    /**
     * @return list<array{string, string}>
     */
    private static function oldSlugsOf(PageInterface $page): array
    {
        /** @var OldSlugRepositoryInterface $oldSlugs */
        $oldSlugs = self::getContainer()->get(OldSlugRepositoryInterface::class);

        return array_map(
            static fn (OldSlugInterface $oldSlug): array => [$oldSlug->getLocaleCode(), $oldSlug->getSlug()],
            $oldSlugs->findByEntry(self::reloadPage($page)),
        );
    }
}
