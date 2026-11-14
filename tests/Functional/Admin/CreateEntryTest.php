<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin;

use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

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
}
